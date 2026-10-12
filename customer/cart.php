<?php
session_start();
require_once "../config/db.php";
require_once "../config/helpers.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$message = "";

/* CART ACTIONS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $product_id = (int)($_POST["product_id"] ?? 0);

    if ($product_id > 0) {

        if ($action === "plus") {

            $stmt = $conn->prepare("
                SELECT quantity
                FROM cart
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
            $cart_item = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($cart_item) {

                $stmt = $conn->prepare("
                    SELECT stock
                    FROM products
                    WHERE id = ? AND status = 'active'
                    LIMIT 1
                ");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($product && $cart_item["quantity"] < $product["stock"]) {

                    $stmt = $conn->prepare("
                        UPDATE cart
                        SET quantity = quantity + 1
                        WHERE user_id = ? AND product_id = ?
                    ");
                    $stmt->bind_param("ii", $user_id, $product_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

        } elseif ($action === "minus") {

            $stmt = $conn->prepare("
                SELECT quantity
                FROM cart
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
            $cart_item = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($cart_item) {

                if ($cart_item["quantity"] > 1) {

                    $stmt = $conn->prepare("
                        UPDATE cart
                        SET quantity = quantity - 1
                        WHERE user_id = ? AND product_id = ?
                    ");
                    $stmt->bind_param("ii", $user_id, $product_id);
                    $stmt->execute();
                    $stmt->close();

                } else {

                    $stmt = $conn->prepare("
                        DELETE FROM cart
                        WHERE user_id = ? AND product_id = ?
                    ");
                    $stmt->bind_param("ii", $user_id, $product_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

        } elseif ($action === "remove") {

            $stmt = $conn->prepare("
                DELETE FROM cart
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: cart.php");
    exit;
}

/* GET CART */
$stmt = $conn->prepare("
    SELECT
        cart.product_id,
        cart.quantity,
        products.name,
        products.description,
        products.price,
        products.discount,
        products.stock,
        products.image
    FROM cart
    INNER JOIN products
        ON cart.product_id = products.id
    WHERE cart.user_id = ?
      AND products.status = 'active'
    ORDER BY cart.created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

$cart_items = [];
$subtotal = 0;
$total_items = 0;

while ($item = $cart_result->fetch_assoc()) {

    $discount_amount =
        ($item["price"] * $item["discount"]) / 100;

    $sale_price =
        $item["price"] - $discount_amount;

    $item_total =
        $sale_price * $item["quantity"];

    $item["sale_price"] = $sale_price;
    $item["item_total"] = $item_total;

    $cart_items[] = $item;

    $subtotal += $item_total;
    $total_items += $item["quantity"];
}

$stmt->close();

/* COUPON ACTIONS */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["apply_coupon"])) {
    $code = strtoupper(trim($_POST["coupon_code"] ?? ""));
    if ($code !== "") {
        $cStmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) LIMIT 1");
        $cStmt->bind_param("s", $code);
        $cStmt->execute();
        $couponData = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();

        if ($couponData) {
            $_SESSION['applied_coupon'] = $couponData;
            $_SESSION['cart_message'] = "Coupon " . htmlspecialchars($couponData['code']) . " applied successfully!";
            $_SESSION['cart_message_type'] = "success";
        } else {
            $_SESSION['cart_message'] = "Invalid or expired coupon code.";
            $_SESSION['cart_message_type'] = "error";
        }
    }
    header("Location: cart.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["remove_coupon"])) {
    unset($_SESSION['applied_coupon']);
    $_SESSION['cart_message'] = "Coupon removed.";
    $_SESSION['cart_message_type'] = "info";
    header("Location: cart.php");
    exit;
}

/* FREE SHIPPING ABOVE ₹999 */
$shipping = ($subtotal > 0 && $subtotal < 999) ? 79 : 0;

/* CALCULATE COUPON DISCOUNT */
$couponDiscount = 0;
$couponError = "";
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;

if ($appliedCoupon && $subtotal > 0) {
    $minCart = (float)$appliedCoupon['minimum_amount'];
    if ($subtotal < $minCart) {
        $couponError = "Coupon " . htmlspecialchars($appliedCoupon['code']) . " requires a minimum order of ₹" . number_format($minCart, 2);
    } else {
        if ($appliedCoupon['discount_type'] === 'percentage') {
            $couponDiscount = round(($subtotal * (float)$appliedCoupon['discount_value']) / 100, 2);
        } else {
            $couponDiscount = min($subtotal, (float)$appliedCoupon['discount_value']);
        }
    }
}

$grand_total = max(0, $subtotal - $couponDiscount) + $shipping;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Cart | QARTIV</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

/* ================= CART PAGE ================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f5f7fc;
    color: #18213d;
}

.cart-page {
    min-height: 100vh;
}

/* HEADER */

.cart-header {
    background: white;
    border-bottom: 1px solid #e7eaf2;
    height: 82px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 55px;
}

.cart-logo {
    font-size: 25px;
    font-weight: 900;
    letter-spacing: 3px;
    color: #172354;
}

.cart-logo span {
    color: #6870ff;
}

.cart-back {
    text-decoration: none;
    color: #68708a;
    font-size: 13px;
    font-weight: 600;
}

.cart-back:hover {
    color: #5965e9;
}

/* CONTENT */

.cart-container {
    max-width: 1200px;
    margin: auto;
    padding: 42px 25px 60px;
}

.cart-title {
    margin-bottom: 30px;
}

.cart-title h1 {
    margin: 0;
    font-size: 30px;
}

.cart-title p {
    margin: 7px 0 0;
    color: #8b92a7;
    font-size: 13px;
}

/* GRID */

.cart-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 25px;
}

/* CART ITEMS */

.cart-items-box,
.cart-summary {
    background: white;
    border: 1px solid #e8ebf3;
    border-radius: 20px;
    box-shadow: 0 8px 28px rgba(30,40,80,.05);
}

.cart-items-box {
    padding: 10px 25px;
}

.cart-item {
    display: grid;
    grid-template-columns: 105px 1fr auto;
    gap: 20px;
    align-items: center;
    padding: 22px 0;
    border-bottom: 1px solid #edf0f5;
}

.cart-item:last-child {
    border-bottom: none;
}

.product-image {
    width: 105px;
    height: 105px;
    border-radius: 16px;
    background: linear-gradient(135deg,#eef0ff,#f7f7ff);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-placeholder {
    font-size: 28px;
    font-weight: 900;
    color: #6570ee;
}

.product-name {
    font-size: 16px;
    font-weight: 750;
    margin-bottom: 7px;
}

.product-description {
    color: #9299ac;
    font-size: 11px;
    margin-bottom: 12px;
}

.price-line {
    display: flex;
    align-items: center;
    gap: 9px;
}

.sale-price {
    font-size: 16px;
    font-weight: 800;
    color: #29335a;
}

.original-price {
    font-size: 11px;
    color: #a1a7b5;
    text-decoration: line-through;
}

.discount-badge {
    font-size: 9px;
    color: #397b59;
    background: #eaf8f0;
    padding: 4px 7px;
    border-radius: 5px;
    font-weight: 700;
}

/* QUANTITY */

.item-right {
    text-align: right;
}

.quantity-box {
    display: inline-flex;
    align-items: center;
    border: 1px solid #dfe3ed;
    border-radius: 10px;
    overflow: hidden;
}

.quantity-box button {
    width: 32px;
    height: 32px;
    border: none;
    background: #f7f8fc;
    cursor: pointer;
    font-size: 16px;
    color: #5965e9;
}

.quantity-box button:hover {
    background: #eef0ff;
}

.quantity-number {
    width: 34px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
}

.item-total {
    margin-top: 12px;
    font-weight: 800;
    font-size: 14px;
}

.remove-btn {
    border: none;
    background: none;
    color: #e47783;
    font-size: 11px;
    margin-top: 7px;
    cursor: pointer;
}

/* SUMMARY */

.cart-summary {
    padding: 25px;
    height: fit-content;
    position: sticky;
    top: 20px;
}

.summary-title {
    font-size: 17px;
    font-weight: 800;
    margin-bottom: 22px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 11px 0;
    color: #747d95;
    font-size: 13px;
}

.summary-row strong {
    color: #222b47;
}

.summary-divider {
    border-top: 1px solid #e9ecf3;
    margin: 10px 0;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    font-size: 18px;
    font-weight: 900;
    padding: 15px 0;
}

.shipping-note {
    background: #f0f4ff;
    color: #5965e9;
    padding: 11px;
    border-radius: 10px;
    font-size: 10px;
    line-height: 1.5;
    margin-bottom: 17px;
}

.checkout-btn {
    width: 100%;
    display: block;
    text-align: center;
    text-decoration: none;
    border: none;
    background: linear-gradient(135deg,#5969ff,#7965ff);
    color: white;
    padding: 14px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(89,105,255,.23);
}

.checkout-btn:hover {
    transform: translateY(-2px);
}

.continue-shopping {
    display: block;
    text-align: center;
    margin-top: 14px;
    color: #6570ee;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
}

/* COUPON STYLES */
.cart-coupon-box {
    margin-bottom: 16px;
}

.coupon-input-group {
    display: flex;
    gap: 8px;
    margin-bottom: 6px;
}

.coupon-text-input {
    flex: 1;
    height: 38px;
    padding: 0 12px;
    border: 1px solid #dcdfe8;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    outline: none;
    color: #1a1e36;
}

.coupon-text-input:focus {
    border-color: #6258ff;
}

.coupon-apply-btn {
    height: 38px;
    padding: 0 14px;
    background: #1a1e36;
    color: #ffffff;
    border: none;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    transition: 0.2s ease;
}

.coupon-apply-btn:hover {
    background: #6258ff;
}

.applied-coupon-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    padding: 9px 12px;
    font-size: 12px;
    color: #065f46;
}

.coupon-tag-value {
    color: #047857;
    font-weight: 800;
    margin-left: 4px;
}

.btn-remove-coupon {
    background: none;
    border: none;
    color: #ef4444;
    font-weight: 800;
    cursor: pointer;
    font-size: 13px;
    padding: 2px 6px;
    border-radius: 4px;
}

.btn-remove-coupon:hover {
    background: #fee2e2;
}

.coupon-hint-text {
    font-size: 11px;
    color: #8c90a4;
    line-height: 1.4;
}

.coupon-error-text {
    font-size: 11px;
    color: #dc2626;
    font-weight: 600;
    line-height: 1.4;
}

/* EMPTY */

.empty-cart {
    text-align: center;
    padding: 80px 20px;
}

.empty-cart-icon {
    width: 75px;
    height: 75px;
    border-radius: 22px;
    background: #eef0ff;
    color: #5965e9;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: auto;
    font-size: 30px;
}

.empty-cart h2 {
    margin: 18px 0 7px;
}

.empty-cart p {
    color: #9198ab;
    font-size: 13px;
}

.shop-btn {
    display: inline-block;
    margin-top: 15px;
    padding: 12px 22px;
    background: #5969ff;
    color: white;
    border-radius: 10px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

/* MOBILE */

@media(max-width:850px) {

    .cart-header {
        padding: 0 20px;
    }

    .cart-container {
        padding: 25px 15px;
    }

    .cart-grid {
        grid-template-columns: 1fr;
    }

    .cart-summary {
        position: static;
    }
}

@media(max-width:550px) {

    .cart-item {
        grid-template-columns: 75px 1fr;
    }

    .product-image {
        width: 75px;
        height: 75px;
    }

    .item-right {
        grid-column: 2;
        text-align: left;
    }

}

</style>

</head>

<body>

<div class="cart-page">

<header class="cart-header">

    <div class="cart-logo">
        QART<span>IV</span>
    </div>

    <a href="products.php" class="cart-back">
        ← Continue Shopping
    </a>

</header>


<main class="cart-container">

<div class="cart-title">

    <h1>My Shopping Cart</h1>

    <p>
        <?php echo $total_items; ?>
        item<?php echo $total_items != 1 ? "s" : ""; ?>
        in your cart
    </p>

</div>


<?php if (count($cart_items) === 0): ?>

<div class="cart-items-box empty-cart">

    <div class="empty-cart-icon">
        🛒
    </div>

    <h2>Your cart is empty</h2>

    <p>
        Looks like you haven't added anything yet.
    </p>

    <a href="products.php" class="shop-btn">
        Start Shopping →
    </a>

</div>

<?php else: ?>


<div class="cart-grid">

<!-- ITEMS -->

<div class="cart-items-box">

<?php foreach ($cart_items as $item): ?>

<div class="cart-item">

    <div class="product-image">
        <a href="product_details.php?id=<?php echo (int)$item["product_id"]; ?>" style="display: block; width: 100%; height: 100%;">
            <img
                src="<?php echo htmlspecialchars(getProductImageUrl($item["image"], "../")); ?>"
                alt="<?php echo htmlspecialchars($item["name"]); ?>"
                loading="lazy"
            >
        </a>
    </div>


    <div>

        <div class="product-name">
            <a href="product_details.php?id=<?php echo (int)$item["product_id"]; ?>" style="color: inherit; text-decoration: none;">
                <?php echo htmlspecialchars($item["name"]); ?>
            </a>
        </div>

        <div class="product-description">
            <?php
            echo htmlspecialchars(
                mb_substr($item["description"] ?? "Quality product from Qartiv.", 0, 70)
            );
            ?>
        </div>

        <div class="price-line">

            <span class="sale-price">
                ₹<?php echo number_format($item["sale_price"], 2); ?>
            </span>

            <?php if ($item["discount"] > 0): ?>

                <span class="original-price">
                    ₹<?php echo number_format($item["price"], 2); ?>
                </span>

                <span class="discount-badge">
                    <?php echo $item["discount"]; ?>% OFF
                </span>

            <?php endif; ?>

        </div>

    </div>


    <div class="item-right">

        <form method="POST">

            <input type="hidden" name="product_id"
                   value="<?php echo $item["product_id"]; ?>">

            <input type="hidden" name="action" value="minus">

            <div class="quantity-box">

                <button type="submit">−</button>

                <span class="quantity-number">
                    <?php echo $item["quantity"]; ?>
                </span>

            </div>

        </form>


        <form method="POST" style="display:inline;">

            <input type="hidden" name="product_id"
                   value="<?php echo $item["product_id"]; ?>">

            <input type="hidden" name="action" value="plus">

            <button
                type="submit"
                style="
                    width:32px;
                    height:32px;
                    border:1px solid #dfe3ed;
                    background:#f7f8fc;
                    border-radius:8px;
                    color:#5965e9;
                    cursor:pointer;
                    margin-left:4px;
                "
            >
                +
            </button>

        </form>

        <div class="item-total">
            ₹<?php echo number_format($item["item_total"], 2); ?>
        </div>

        <form method="POST">

            <input type="hidden" name="product_id"
                   value="<?php echo $item["product_id"]; ?>">

            <input type="hidden" name="action" value="remove">

            <button type="submit" class="remove-btn">
                Remove
            </button>

        </form>

    </div>

</div>

<?php endforeach; ?>

</div>


<!-- SUMMARY -->

<div class="cart-summary">

    <div class="summary-title">
        Order Summary
    </div>

    <!-- COUPON BOX -->
    <div class="cart-coupon-box">
        <?php if (!empty($appliedCoupon) && $couponDiscount > 0): ?>
            <div class="applied-coupon-pill">
                <div>
                    <span class="coupon-tag-icon">🏷️</span>
                    <strong><?php echo htmlspecialchars($appliedCoupon['code']); ?></strong>
                    <span class="coupon-tag-value">(-₹<?php echo number_format($couponDiscount, 2); ?>)</span>
                </div>
                <form method="POST" style="margin:0;">
                    <button type="submit" name="remove_coupon" class="btn-remove-coupon" title="Remove coupon">✕</button>
                </form>
            </div>
        <?php else: ?>
            <form method="POST" class="coupon-input-group">
                <input 
                    type="text" 
                    name="coupon_code" 
                    placeholder="Enter Coupon (e.g. WELCOME10)" 
                    class="coupon-text-input" 
                    required>
                <button type="submit" name="apply_coupon" class="coupon-apply-btn">Apply</button>
            </form>
            <?php if (!empty($couponError)): ?>
                <div class="coupon-error-text"><?php echo htmlspecialchars($couponError); ?></div>
            <?php else: ?>
                <div class="coupon-hint-text">💡 Try code <strong>WELCOME10</strong> (10% off) or <strong>QARTIV500</strong></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="summary-divider"></div>

    <div class="summary-row">

        <span>Subtotal</span>

        <strong>
            ₹<?php echo number_format($subtotal, 2); ?>
        </strong>

    </div>

    <?php if ($couponDiscount > 0): ?>
    <div class="summary-row" style="color: #10b981;">

        <span>Coupon Discount</span>

        <strong>
            -₹<?php echo number_format($couponDiscount, 2); ?>
        </strong>

    </div>
    <?php endif; ?>

    <div class="summary-row">

        <span>Shipping</span>

        <strong>
            <?php
            echo $shipping == 0
                ? "FREE"
                : "₹" . number_format($shipping, 2);
            ?>
        </strong>

    </div>

    <div class="summary-divider"></div>

    <div class="summary-total">

        <span>Total</span>

        <span>
            ₹<?php echo number_format($grand_total, 2); ?>
        </span>

    </div>


    <?php if ($shipping == 0): ?>

        <div class="shipping-note">
            🎉 Congratulations! You qualify for free shipping.
        </div>

    <?php else: ?>

        <div class="shipping-note">
            Add ₹<?php echo number_format(999 - $subtotal, 2); ?>
            more to unlock FREE shipping.
        </div>

    <?php endif; ?>


    <a href="checkout.php" class="checkout-btn">
        Proceed to Checkout →
    </a>

    <a href="products.php" class="continue-shopping">
        Continue Shopping
    </a>

</div>

</div>

<?php endif; ?>

</main>

</div>

</body>
</html>