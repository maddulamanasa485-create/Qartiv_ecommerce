<?php

session_start();
require_once "../config/db.php";
require_once "../config/helpers.php";

/* =========================================================
   CUSTOMER ACCESS
========================================================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$userName = $_SESSION["user_name"] ?? "Customer";

$message = "";
$messageType = "";


/* =========================================================
   FETCH CART
========================================================= */

$cartItems = [];
$subtotal = 0;

$stmt = $conn->prepare(
    "SELECT
        c.id AS cart_id,
        c.product_id,
        c.quantity,
        p.name,
        p.price,
        p.discount,
        p.stock,
        p.image
     FROM cart c
     INNER JOIN products p
        ON c.product_id = p.id
     WHERE c.user_id = ?
     ORDER BY c.id DESC"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $price = (float) $row["price"];
    $discount = (float) $row["discount"];
    $quantity = (int) $row["quantity"];

    $salePrice = $price;

    if ($discount > 0) {
        $salePrice = $price - ($price * $discount / 100);
    }

    $itemTotal = $salePrice * $quantity;

    $row["sale_price"] = $salePrice;
    $row["item_total"] = $itemTotal;

    $cartItems[] = $row;

    $subtotal += $itemTotal;
}

$stmt->close();


/* =========================================================
   IF CART EMPTY
========================================================= */

if (empty($cartItems)) {
    header("Location: cart.php");
    exit;
}


/* =========================================================
   SHIPPING & COUPON DISCOUNT
========================================================= */

$shipping = 0;

if ($subtotal > 0 && $subtotal < 999) {
    $shipping = 79;
}

$couponDiscount = 0;
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
if ($appliedCoupon && $subtotal > 0) {
    $minCart = (float)$appliedCoupon['minimum_amount'];
    if ($subtotal >= $minCart) {
        if ($appliedCoupon['discount_type'] === 'percentage') {
            $couponDiscount = round(($subtotal * (float)$appliedCoupon['discount_value']) / 100, 2);
        } else {
            $couponDiscount = min($subtotal, (float)$appliedCoupon['discount_value']);
        }
    }
}

$grandTotal = max(0, $subtotal - $couponDiscount) + $shipping;


/* =========================================================
   PLACE ORDER
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["place_order"])) {

    $fullName = trim($_POST["full_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $city = trim($_POST["city"] ?? "");
    $state = trim($_POST["state"] ?? "");
    $pincode = trim($_POST["pincode"] ?? "");
    $paymentMethod = strtoupper(trim($_POST["payment_method"] ?? "COD"));
    $validMethods = ["COD", "UPI", "CARD"];
    if (!in_array($paymentMethod, $validMethods, true)) {
        $paymentMethod = "COD";
    }

    /* Validate */

    if (
        $fullName === "" ||
        $phone === "" ||
        $address === "" ||
        $city === "" ||
        $state === "" ||
        $pincode === ""
    ) {

        $message = "Please fill all delivery details.";
        $messageType = "error";

    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {

        $message = "Please enter a valid 10-digit phone number.";
        $messageType = "error";

    } elseif (!preg_match("/^[0-9]{6}$/", $pincode)) {

        $message = "Please enter a valid 6-digit pincode.";
        $messageType = "error";

    } else {

        /* =============================================
           START TRANSACTION
        ============================================= */

        $conn->begin_transaction();

        try {

            /* =============================================
               CREATE ADDRESS
            ============================================= */

            $addressStmt = $conn->prepare(
                "INSERT INTO addresses
                (
                    user_id,
                    full_name,
                    phone,
                    address,
                    city,
                    state,
                    pincode
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $addressStmt->bind_param(
                "issssss",
                $userId,
                $fullName,
                $phone,
                $address,
                $city,
                $state,
                $pincode
            );

            if (!$addressStmt->execute()) {
                throw new Exception("Unable to save address.");
            }

            $addressId = $conn->insert_id;

            $addressStmt->close();


            /* =============================================
               CREATE ORDER
            ============================================= */

            $paymentStatus = ($paymentMethod === "COD") ? "pending" : "paid";

            $orderStmt = $conn->prepare(
                "INSERT INTO orders
                (
                    user_id,
                    address_id,
                    total_amount,
                    payment_method,
                    payment_status,
                    order_status
                )
                VALUES (?, ?, ?, ?, ?, 'placed')"
            );

            $orderStmt->bind_param(
                "iidss",
                $userId,
                $addressId,
                $grandTotal,
                $paymentMethod,
                $paymentStatus
            );

            if (!$orderStmt->execute()) {
                throw new Exception("Unable to create order.");
            }

            $orderId = $conn->insert_id;

            $orderStmt->close();


            /* =============================================
               CREATE ORDER ITEMS
            ============================================= */

            $itemStmt = $conn->prepare(
                "INSERT INTO order_items
                (
                    order_id,
                    product_id,
                    quantity,
                    price
                )
                VALUES (?, ?, ?, ?)"
            );


            $stockStmt = $conn->prepare(
                "UPDATE products
                 SET stock = stock - ?
                 WHERE id = ?
                 AND stock >= ?"
            );


            foreach ($cartItems as $item) {

                $productId = (int) $item["product_id"];
                $quantity = (int) $item["quantity"];
                $price = (float) $item["sale_price"];
                $availableStock = (int) $item["stock"];


                /* Check stock again */

                if ($quantity > $availableStock) {

                    throw new Exception(
                        "Some products do not have enough stock."
                    );
                }


                /* Order item */

                $itemStmt->bind_param(
                    "iiid",
                    $orderId,
                    $productId,
                    $quantity,
                    $price
                );

                if (!$itemStmt->execute()) {
                    throw new Exception(
                        "Unable to save order items."
                    );
                }


                /* Reduce stock */

                $stockStmt->bind_param(
                    "iii",
                    $quantity,
                    $productId,
                    $quantity
                );

                if (!$stockStmt->execute()) {
                    throw new Exception(
                        "Unable to update product stock."
                    );
                }

                if ($stockStmt->affected_rows === 0) {
                    throw new Exception(
                        "Product stock is insufficient."
                    );
                }
            }

            $itemStmt->close();
            $stockStmt->close();


            /* =============================================
               CLEAR CART
            ============================================= */

            $clearStmt = $conn->prepare(
                "DELETE FROM cart
                 WHERE user_id = ?"
            );

            $clearStmt->bind_param(
                "i",
                $userId
            );

            if (!$clearStmt->execute()) {
                throw new Exception(
                    "Unable to clear cart."
                );
            }

            $clearStmt->close();


            /* =============================================
               COMMIT
            ============================================= */

            $conn->commit();
            unset($_SESSION['applied_coupon']);


            /* =============================================
               ORDER SUCCESS
            ============================================= */

            header(
                "Location: order_success.php?id=" .
                $orderId
            );

            exit;


        } catch (Exception $e) {

            $conn->rollback();

            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Checkout | QARTIV</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Inter,
                Arial,
                sans-serif;

            background:
                #f5f7fb;

            color:
                #172033;
        }

        a {
            text-decoration: none;
        }

        /* =========================================
           HEADER
        ========================================= */

        .checkout-header {
            height: 76px;

            background:
                #ffffff;

            border-bottom:
                1px solid #e8ebf2;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                0 6%;

            position:
                sticky;

            top: 0;

            z-index: 10;
        }

        .checkout-brand {
            display:
                flex;

            align-items:
                center;

            gap: 12px;
        }

        .checkout-logo {
            width: 42px;
            height: 42px;

            border-radius: 12px;

            display:
                flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #4169ff,
                    #7a45ff
                );

            color: white;

            font-size: 22px;

            font-weight: 800;
        }

        .checkout-brand-text strong {
            display: block;

            font-size: 19px;

            letter-spacing: 2px;

            color:
                #151b2e;
        }

        .checkout-brand-text span {
            display: block;

            font-size: 9px;

            letter-spacing: 1.5px;

            color:
                #8992a7;
        }

        .back-shopping {
            color:
                #4b5c7a;

            font-weight:
                600;

            font-size:
                14px;
        }

        .back-shopping:hover {
            color:
                #4264ff;
        }


        /* =========================================
           MAIN
        ========================================= */

        .checkout-main {
            max-width:
                1200px;

            margin:
                0 auto;

            padding:
                42px 25px 70px;
        }

        .checkout-title {
            margin-bottom:
                32px;
        }

        .checkout-title span {
            font-size:
                11px;

            letter-spacing:
                2px;

            color:
                #596cff;

            font-weight:
                800;
        }

        .checkout-title h1 {
            font-size:
                36px;

            margin-top:
                8px;

            color:
                #172033;
        }

        .checkout-title p {
            color:
                #7b8497;

            margin-top:
                8px;
        }


        /* =========================================
           MESSAGE
        ========================================= */

        .checkout-message {
            padding:
                14px 18px;

            border-radius:
                12px;

            margin-bottom:
                25px;

            font-weight:
                600;
        }

        .checkout-message.error {
            background:
                #fff0f0;

            color:
                #d93434;

            border:
                1px solid #ffd4d4;
        }


        /* =========================================
           LAYOUT
        ========================================= */

        .checkout-layout {
            display:
                grid;

            grid-template-columns:
                1fr 390px;

            gap:
                28px;

            align-items:
                start;
        }


        /* =========================================
           FORM CARD
        ========================================= */

        .checkout-card {
            background:
                #ffffff;

            border:
                1px solid #e7eaf1;

            border-radius:
                20px;

            padding:
                28px;

            box-shadow:
                0 12px 35px rgba(
                    31,
                    45,
                    72,
                    0.06
                );
        }

        .checkout-card-title {
            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            margin-bottom:
                25px;
        }

        .checkout-card-number {
            width: 34px;
            height: 34px;

            border-radius: 10px;

            background:
                #eef1ff;

            color:
                #5266ff;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-weight:
                800;
        }

        .checkout-card-title h2 {
            font-size:
                19px;
        }


        /* =========================================
           FORM
        ========================================= */

        .form-grid {
            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                18px;
        }

        .form-group {
            margin-bottom:
                18px;
        }

        .form-group.full {
            grid-column:
                1 / -1;
        }

        .form-group label {
            display:
                block;

            margin-bottom:
                7px;

            font-size:
                13px;

            font-weight:
                700;

            color:
                #39445a;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {

            width: 100%;

            border:
                1px solid #dfe4ed;

            border-radius:
                11px;

            padding:
                13px 14px;

            font-size:
                14px;

            outline:
                none;

            background:
                #fbfcfe;

            color:
                #20283b;
        }

        .form-group textarea {
            min-height:
                100px;

            resize:
                vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {

            border-color:
                #6374ff;

            background:
                #ffffff;

            box-shadow:
                0 0 0 3px
                rgba(
                    99,
                    116,
                    255,
                    0.09
                );
        }


        /* =========================================
           PAYMENT METHODS
        ========================================= */

        .payment-options-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .payment-option {
            border: 1.5px solid #e2e8f0;
            background: #ffffff;
            border-radius: 14px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .payment-option:hover {
            border-color: #6374ff;
            background: #fafbff;
        }

        .payment-option.active {
            border: 2px solid #6374ff;
            background: #f5f6ff;
        }

        .payment-option input {
            accent-color: #6374ff;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .payment-icon {
            font-size: 24px;
            line-height: 1;
        }

        .payment-text strong {
            display: block;
            font-size: 14px;
            color: #1e293b;
        }

        .payment-text span {
            font-size: 12px;
            color: #7d8799;
        }

        .payment-extra-panel {
            margin-top: 10px;
            padding: 14px 16px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            font-size: 12.5px;
            color: #475569;
            display: none;
        }

        .payment-extra-panel.visible {
            display: block;
        }

        .payment-badges-row {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .payment-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            background: #e2e8f0;
            color: #1e293b;
        }


        /* =========================================
           SUMMARY
        ========================================= */

        .summary-card {
            background:
                #ffffff;

            border:
                1px solid #e7eaf1;

            border-radius:
                20px;

            padding:
                25px;

            box-shadow:
                0 12px 35px rgba(
                    31,
                    45,
                    72,
                    0.06
                );

            position:
                sticky;

            top:
                100px;
        }

        .summary-card h2 {
            font-size:
                20px;

            margin-bottom:
                22px;
        }

        .summary-product {
            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            padding:
                12px 0;

            border-bottom:
                1px solid #edf0f5;
        }

        .summary-image {
            width:
                55px;

            height:
                55px;

            border-radius:
                10px;

            background:
                #f1f3f8;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            overflow:
                hidden;
        }

        .summary-image img {
            width:
                100%;

            height:
                100%;

            object-fit:
                cover;
        }

        .summary-placeholder {
            font-size:
                22px;

            font-weight:
                800;

            color:
                #6878ff;
        }

        .summary-product-info {
            flex:
                1;
        }

        .summary-product-info strong {
            display:
                block;

            font-size:
                13px;
        }

        .summary-product-info span {
            font-size:
                12px;

            color:
                #8991a1;
        }

        .summary-product-price {
            font-weight:
                700;

            font-size:
                13px;
        }


        /* =========================================
           TOTALS
        ========================================= */

        .summary-totals {
            margin-top:
                20px;
        }

        .summary-row {
            display:
                flex;

            justify-content:
                space-between;

            padding:
                8px 0;

            font-size:
                14px;
        }

        .summary-row span:first-child {
            color:
                #778195;
        }

        .summary-row strong {
            color:
                #20283b;
        }

        .summary-total {
            border-top:
                1px solid #e6e9ef;

            margin-top:
                10px;

            padding-top:
                17px;

            font-size:
                19px;
        }

        .summary-total strong {
            color:
                #5366ff;

            font-size:
                22px;
        }


        /* =========================================
           FREE SHIPPING
        ========================================= */

        .free-shipping {
            margin-top:
                18px;

            padding:
                12px;

            background:
                #effcf5;

            color:
                #16834d;

            border-radius:
                10px;

            font-size:
                12px;

            font-weight:
                600;
        }


        /* =========================================
           PLACE ORDER
        ========================================= */

        .place-order-btn {
            width:
                100%;

            border:
                none;

            margin-top:
                20px;

            padding:
                15px;

            border-radius:
                12px;

            background:
                linear-gradient(
                    135deg,
                    #5268ff,
                    #7548ff
                );

            color:
                #ffffff;

            font-size:
                15px;

            font-weight:
                800;

            cursor:
                pointer;

            box-shadow:
                0 10px 24px
                rgba(
                    82,
                    104,
                    255,
                    0.22
                );
        }

        .place-order-btn:hover {
            transform:
                translateY(-1px);

            box-shadow:
                0 14px 28px
                rgba(
                    82,
                    104,
                    255,
                    0.28
                );
        }

        .secure-note {
            text-align:
                center;

            margin-top:
                13px;

            color:
                #8a93a5;

            font-size:
                11px;
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 850px) {

            .checkout-layout {
                grid-template-columns:
                    1fr;
            }

            .summary-card {
                position:
                    static;
            }

        }

        @media (max-width: 600px) {

            .checkout-header {
                padding:
                    0 18px;
            }

            .back-shopping {
                font-size:
                    12px;
            }

            .checkout-main {
                padding:
                    30px 15px;
            }

            .checkout-title h1 {
                font-size:
                    29px;
            }

            .checkout-card {
                padding:
                    20px;
            }

            .form-grid {
                grid-template-columns:
                    1fr;
            }

            .form-group.full {
                grid-column:
                    auto;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="checkout-header">

    <a
        href="dashboard.php"
        class="checkout-brand">

        <div class="checkout-logo">
            Q
        </div>

        <div class="checkout-brand-text">

            <strong>QARTIV</strong>

            <span>
                SMART COMMERCE
            </span>

        </div>

    </a>


    <a
        href="products.php"
        class="back-shopping">

        ← Continue Shopping

    </a>

</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="checkout-main">


    <div class="checkout-title">

        <span>
            QARTIV CHECKOUT
        </span>

        <h1>
            Complete your order
        </h1>

        <p>
            Enter your delivery details and confirm your purchase.
        </p>

    </div>


    <?php if ($message !== ""): ?>

        <div
            class="checkout-message <?php echo htmlspecialchars($messageType); ?>">

            ⚠
            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        action="checkout.php">

        <div class="checkout-layout">


            <!-- =============================================
                 LEFT
            ============================================== -->

            <div>


                <!-- DELIVERY -->

                <section class="checkout-card">

                    <div class="checkout-card-title">

                        <div class="checkout-card-number">
                            1
                        </div>

                        <h2>
                            Delivery Information
                        </h2>

                    </div>


                    <div class="form-grid">


                        <div class="form-group full">

                            <label for="full_name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                placeholder="Enter your full name"
                                value="<?php echo htmlspecialchars($_POST["full_name"] ?? $userName); ?>"
                                required>

                        </div>


                        <div class="form-group">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                placeholder="10-digit mobile number"
                                maxlength="10"
                                value="<?php echo htmlspecialchars($_POST["phone"] ?? ""); ?>"
                                required>

                        </div>


                        <div class="form-group">

                            <label for="pincode">
                                Pincode
                            </label>

                            <input
                                type="text"
                                id="pincode"
                                name="pincode"
                                placeholder="6-digit pincode"
                                maxlength="6"
                                value="<?php echo htmlspecialchars($_POST["pincode"] ?? ""); ?>"
                                required>

                        </div>


                        <div class="form-group full">

                            <label for="address">
                                Complete Address
                            </label>

                            <textarea
                                id="address"
                                name="address"
                                placeholder="House / Flat No, Street, Area"
                                required><?php echo htmlspecialchars($_POST["address"] ?? ""); ?></textarea>

                        </div>


                        <div class="form-group">

                            <label for="city">
                                City
                            </label>

                            <input
                                type="text"
                                id="city"
                                name="city"
                                placeholder="Enter city"
                                value="<?php echo htmlspecialchars($_POST["city"] ?? ""); ?>"
                                required>

                        </div>


                        <div class="form-group">

                            <label for="state">
                                State
                            </label>

                            <input
                                type="text"
                                id="state"
                                name="state"
                                placeholder="Enter state"
                                value="<?php echo htmlspecialchars($_POST["state"] ?? ""); ?>"
                                required>

                        </div>

                    </div>

                </section>


                <!-- PAYMENT -->

                <section
                    class="checkout-card"
                    style="margin-top: 22px;">

                    <div class="checkout-card-title">

                        <div class="checkout-card-number">
                            2
                        </div>

                        <h2>
                            Payment Method
                        </h2>

                    </div>


                    <div class="payment-options-list">

                        <!-- COD -->
                        <label class="payment-option active" id="optCOD">
                            <input
                                type="radio"
                                name="payment_method"
                                value="COD"
                                checked
                                onchange="selectPaymentMethod('COD')">
                            <span class="payment-icon">💵</span>
                            <span class="payment-text">
                                <strong>Cash on Delivery (COD)</strong>
                                <span>Pay securely with cash or UPI upon delivery.</span>
                            </span>
                        </label>

                        <!-- UPI -->
                        <label class="payment-option" id="optUPI">
                            <input
                                type="radio"
                                name="payment_method"
                                value="UPI"
                                onchange="selectPaymentMethod('UPI')">
                            <span class="payment-icon">⚡</span>
                            <span class="payment-text">
                                <strong>Instant UPI / QR Code</strong>
                                <span>Google Pay, PhonePe, Paytm, BHIM & UPI Apps.</span>
                            </span>
                        </label>
                        <div class="payment-extra-panel" id="panelUPI">
                            <div style="font-weight:600;color:#0f172a;margin-bottom:6px;">⚡ Instant Simulated UPI Payment</div>
                            <div>Orders paid via UPI are verified instantly and marked <strong>Prepaid (PAID)</strong> with priority dispatch.</div>
                            <div class="payment-badges-row">
                                <span class="payment-pill">GPay</span>
                                <span class="payment-pill">PhonePe</span>
                                <span class="payment-pill">Paytm</span>
                                <span class="payment-pill">BHIM</span>
                            </div>
                        </div>

                        <!-- Card -->
                        <label class="payment-option" id="optCARD">
                            <input
                                type="radio"
                                name="payment_method"
                                value="CARD"
                                onchange="selectPaymentMethod('CARD')">
                            <span class="payment-icon">💳</span>
                            <span class="payment-text">
                                <strong>Credit & Debit Cards</strong>
                                <span>Visa, MasterCard, RuPay, Maestro, American Express.</span>
                            </span>
                        </label>
                        <div class="payment-extra-panel" id="panelCARD">
                            <div style="font-weight:600;color:#0f172a;margin-bottom:6px;">🔒 256-Bit Encrypted Card Payment</div>
                            <div>Instant authorization simulation. Marked <strong>Prepaid (PAID)</strong> with official digital tax invoice.</div>
                            <div class="payment-badges-row">
                                <span class="payment-pill">VISA</span>
                                <span class="payment-pill">Mastercard</span>
                                <span class="payment-pill">RuPay</span>
                            </div>
                        </div>

                    </div>

                    <script>
                    function selectPaymentMethod(method) {
                        ['COD', 'UPI', 'CARD'].forEach(function(m) {
                            var el = document.getElementById('opt' + m);
                            var panel = document.getElementById('panel' + m);
                            if (el) el.classList.remove('active');
                            if (panel) panel.classList.remove('visible');
                        });
                        var activeEl = document.getElementById('opt' + method);
                        var activePanel = document.getElementById('panel' + method);
                        if (activeEl) activeEl.classList.add('active');
                        if (activePanel) activePanel.classList.add('visible');
                    }
                    </script>

                </section>

            </div>


            <!-- =============================================
                 RIGHT SUMMARY
            ============================================== -->

            <aside class="summary-card">

                <h2>
                    Order Summary
                </h2>


                <?php foreach ($cartItems as $item): ?>

                    <div class="summary-product">


                        <div class="summary-image">
                            <img
                                src="<?php echo htmlspecialchars(getProductImageUrl($item["image"], "../")); ?>"
                                alt="<?php echo htmlspecialchars($item["name"]); ?>"
                                loading="lazy">
                        </div>


                        <div class="summary-product-info">

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $item["name"]
                                );
                                ?>

                            </strong>

                            <span>

                                Qty:
                                <?php echo (int) $item["quantity"]; ?>

                            </span>

                        </div>


                        <div class="summary-product-price">

                            ₹<?php
                            echo number_format(
                                $item["item_total"],
                                2
                            );
                            ?>

                        </div>

                    </div>

                <?php endforeach; ?>


                <div class="summary-totals">


                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>

                        <strong>
                            ₹<?php
                            echo number_format(
                                $subtotal,
                                2
                            );
                            ?>
                        </strong>

                    </div>

                    <?php if ($couponDiscount > 0): ?>
                        <div class="summary-row" style="color: #10b981;">
                            <span>Coupon (<?php echo htmlspecialchars($appliedCoupon['code'] ?? 'PROMO'); ?>)</span>
                            <strong>-₹<?php echo number_format($couponDiscount, 2); ?></strong>
                        </div>
                    <?php endif; ?>


                    <div class="summary-row">

                        <span>
                            Shipping
                        </span>

                        <strong>

                            <?php if ($shipping === 0): ?>

                                FREE

                            <?php else: ?>

                                ₹<?php
                                echo number_format(
                                    $shipping,
                                    2
                                );
                                ?>

                            <?php endif; ?>

                        </strong>

                    </div>


                    <div class="summary-row summary-total">

                        <span>
                            Total
                        </span>

                        <strong>
                            ₹<?php
                            echo number_format(
                                $grandTotal,
                                2
                            );
                            ?>
                        </strong>

                    </div>

                </div>


                <?php if ($shipping === 0): ?>

                    <div class="free-shipping">

                        🎉 You qualify for free shipping!

                    </div>

                <?php endif; ?>


                <button
                    type="submit"
                    name="place_order"
                    class="place-order-btn">

                    Place Order →
                    
                </button>


                <div class="secure-note">

                    🔒 Secure checkout • Your information is protected

                </div>

            </aside>

        </div>

    </form>

</main>


</body>
</html>