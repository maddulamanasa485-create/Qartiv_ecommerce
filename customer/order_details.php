<?php
session_start();
require_once "../config/db.php";
require_once "../config/helpers.php";

/* USER ACCESS CHECK: CUSTOMER OR ADMIN */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_role = $_SESSION["user_role"] ?? "customer";
$user_id   = (int)$_SESSION["user_id"];

/* GET ORDER ID */
$order_id = intval($_GET["id"] ?? 0);

if ($order_id <= 0) {
    header("Location: " . ($user_role === "admin" ? "../admin/orders.php" : "orders.php"));
    exit;
}

/* FETCH ORDER + ADDRESS (Admin can view any order, Customer can view own) */
if ($user_role === "admin") {
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.created_at,
            a.full_name,
            a.phone,
            a.address,
            a.city,
            a.state,
            a.pincode
        FROM orders o
        LEFT JOIN addresses a ON o.address_id = a.id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $order_id);
} else {
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.created_at,
            a.full_name,
            a.phone,
            a.address,
            a.city,
            a.state,
            a.pincode
        FROM orders o
        LEFT JOIN addresses a ON o.address_id = a.id
        WHERE o.id = ? AND o.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
}
$stmt->execute();

$order_result = $stmt->get_result();

if ($order_result->num_rows !== 1) {
    $stmt->close();
    header("Location: " . ($user_role === "admin" ? "../admin/orders.php" : "orders.php"));
    exit;
}

$order = $order_result->fetch_assoc();
$stmt->close();


/* FETCH ORDER ITEMS */
$stmt = $conn->prepare("
    SELECT
        oi.product_id,
        oi.quantity,
        oi.price,
        p.name,
        p.image
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");

$stmt->bind_param("i", $order_id);
$stmt->execute();

$items_result = $stmt->get_result();

$items = [];

while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}

$stmt->close();


/* STATUS CLASS */
$status = strtolower($order["order_status"]);
$status_class = "status-" . $status;


/* DATE */
$order_date = date(
    "d M Y, h:i A",
    strtotime($order["created_at"])
);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Order Details | QARTIV
    </title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #172033;
        }

        a {
            text-decoration: none;
        }


        /* HEADER */

        .header {
            height: 76px;
            background: #ffffff;
            border-bottom: 1px solid #e7eaf1;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 6%;
        }

        .brand {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #202b6b;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .user-name {
            color: #697185;
            font-size: 14px;
            font-weight: 600;
        }

        .logout {
            padding: 10px 17px;
            border-radius: 9px;

            background: #eef0ff;
            color: #3946a8;

            font-size: 13px;
            font-weight: 700;
        }

        .logout:hover {
            background: #3946a8;
            color: white;
        }


        /* MAIN */

        .container {
            width: 88%;
            max-width: 1200px;
            margin: 40px auto;
        }


        /* TOP */

        .back-link {
            display: inline-block;
            margin-bottom: 20px;

            color: #3946a8;
            font-size: 14px;
            font-weight: 700;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;

            margin-bottom: 25px;
        }

        .page-heading h1 {
            font-size: 30px;
            color: #18213c;
        }

        .page-heading p {
            margin-top: 7px;
            color: #7b8395;
            font-size: 14px;
        }


        /* STATUS */

        .status {
            padding: 9px 17px;
            border-radius: 30px;

            font-size: 12px;
            font-weight: 700;

            text-transform: capitalize;
        }

        .status-placed {
            background: #fff4d8;
            color: #9a6b00;
        }

        .status-confirmed {
            background: #e8f0ff;
            color: #315cc7;
        }

        .status-shipped {
            background: #eee8ff;
            color: #7047c7;
        }

        .status-delivered {
            background: #e4f8ed;
            color: #198754;
        }

        .status-cancelled {
            background: #ffe7e7;
            color: #d33a3a;
        }


        /* SUMMARY */

        .summary-card {
            background: white;
            border: 1px solid #e6e9f0;
            border-radius: 18px;

            padding: 24px;
            margin-bottom: 20px;

            box-shadow: 0 7px 25px rgba(35,45,80,.05);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .summary-label {
            font-size: 11px;
            color: #9299aa;

            text-transform: uppercase;
            letter-spacing: .7px;

            margin-bottom: 7px;
        }

        .summary-value {
            font-size: 15px;
            font-weight: 700;
            color: #252d43;
        }

        .summary-value.amount {
            font-size: 19px;
            color: #202b6b;
        }


        /* GRID */

        .details-grid {
            display: grid;
            grid-template-columns: 1.7fr 1fr;
            gap: 20px;
        }


        /* CARD */

        .card {
            background: white;
            border: 1px solid #e6e9f0;
            border-radius: 18px;

            padding: 25px;

            box-shadow: 0 7px 25px rgba(35,45,80,.05);
        }

        .card-title {
            font-size: 18px;
            font-weight: 800;
            color: #202940;

            margin-bottom: 20px;
        }


        /* PRODUCTS */

        .product-row {
            display: flex;
            align-items: center;

            padding: 16px 0;

            border-bottom: 1px solid #edf0f5;
        }

        .product-row:last-child {
            border-bottom: none;
        }

        .product-image {
            width: 70px;
            height: 70px;

            border-radius: 12px;

            object-fit: cover;

            background: #f1f3f8;

            margin-right: 16px;
        }

        .product-placeholder {
            width: 70px;
            height: 70px;

            border-radius: 12px;

            background: #f1f3f8;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 25px;

            margin-right: 16px;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-size: 15px;
            font-weight: 700;
            color: #252d43;

            margin-bottom: 7px;
        }

        .product-quantity {
            font-size: 13px;
            color: #7d8597;
        }

        .product-price {
            font-size: 15px;
            font-weight: 800;
            color: #202b6b;
        }


        /* ADDRESS */

        .address-box {
            background: #f8f9fc;
            border-radius: 13px;
            padding: 18px;
        }

        .address-name {
            font-size: 16px;
            font-weight: 800;
            color: #202940;
            margin-bottom: 9px;
        }

        .address-line {
            color: #6e7689;
            font-size: 13px;
            line-height: 1.7;
        }


        /* PAYMENT */

        .payment-box {
            margin-top: 20px;
        }

        .info-line {
            display: flex;
            justify-content: space-between;

            padding: 11px 0;

            border-bottom: 1px solid #edf0f5;

            font-size: 13px;
        }

        .info-line:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #7d8597;
        }

        .info-value {
            font-weight: 700;
            color: #30384d;
        }


        /* TOTAL */

        .total-box {
            margin-top: 20px;

            padding-top: 18px;

            border-top: 2px solid #e9ecf3;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 15px;
            font-weight: 700;
        }

        .total-value {
            font-size: 23px;
            font-weight: 800;
            color: #202b6b;
        }


        /* BUTTON */

        .orders-btn {
            display: inline-block;

            margin-top: 25px;

            padding: 12px 20px;

            background: #202b6b;
            color: white;

            border-radius: 10px;

            font-size: 13px;
            font-weight: 700;
        }

        .orders-btn:hover {
            background: #3946a8;
        }


        /* RESPONSIVE */

        @media (max-width: 800px) {

            .container {
                width: 92%;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .page-heading {
                flex-direction: column;
                gap: 15px;
            }

        }


        @media (max-width: 500px) {

            .header {
                padding: 0 20px;
            }

            .user-name {
                display: none;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .product-price {
                font-size: 13px;
            }

        }

    </style>

</head>

<body>


<!-- HEADER -->

<header class="header">

    <a href="<?php echo $user_role === 'admin' ? '../admin/dashboard.php' : 'dashboard.php'; ?>" class="brand">
        QARTIV
    </a>

    <div class="header-right">

        <span class="user-name">
            Hi, <?php echo htmlspecialchars($_SESSION["user_name"] ?? ($user_role === 'admin' ? 'Administrator' : 'Customer')); ?>
        </span>

        <a href="../auth/logout.php" class="logout">
            Logout
        </a>

    </div>

</header>


<!-- MAIN -->

<main class="container">


    <a href="<?php echo $user_role === 'admin' ? '../admin/orders.php' : 'orders.php'; ?>" class="back-link">
        ← <?php echo $user_role === 'admin' ? 'Back to Order Management' : 'Back to My Orders'; ?>
    </a>


    <!-- HEADING -->

    <div class="page-heading">

        <div>

            <h1>
                Order #QRT-<?php
                echo str_pad(
                    $order["id"],
                    5,
                    "0",
                    STR_PAD_LEFT
                );
                ?>
            </h1>

            <p>
                Placed on <?php echo $order_date; ?>
            </p>

        </div>


        <div style="display:flex;align-items:center;gap:12px;">
            <a href="invoice.php?id=<?php echo $order_id; ?>" target="_blank" style="padding:8px 14px;border:1px solid #d4af37;background:#fff;color:#1a1e36;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                📄 Invoice
            </a>
            <span class="status <?php echo $status_class; ?>">
                <?php echo htmlspecialchars($order["order_status"]); ?>
            </span>
        </div>

    </div>


    <!-- ORDER SUMMARY -->

    <div class="summary-card">

        <div class="summary-grid">


            <div>

                <div class="summary-label">
                    Order Total
                </div>

                <div class="summary-value amount">

                    ₹<?php
                    echo number_format(
                        $order["total_amount"],
                        2
                    );
                    ?>

                </div>

            </div>


            <div>

                <div class="summary-label">
                    Payment Method
                </div>

                <div class="summary-value">

                    <?php
                    echo htmlspecialchars(
                        $order["payment_method"]
                    );
                    ?>

                </div>

            </div>


            <div>

                <div class="summary-label">
                    Payment Status
                </div>

                <div class="summary-value">

                    <?php
                    echo ucfirst(
                        htmlspecialchars(
                            $order["payment_status"]
                        )
                    );
                    ?>

                </div>

            </div>


            <div>

                <div class="summary-label">
                    Items
                </div>

                <div class="summary-value">

                    <?php echo count($items); ?>

                    Product<?php
                    echo count($items) != 1 ? "s" : "";
                    ?>

                </div>

            </div>

        </div>

    </div>


    <!-- DETAILS -->

    <div class="details-grid">


        <!-- PRODUCTS -->

        <div class="card">

            <h2 class="card-title">
                Ordered Products
            </h2>


            <?php foreach ($items as $item): ?>

                <div class="product-row">


                    <a href="product_details.php?id=<?php echo (int)$item["product_id"]; ?>">
                        <img
                            src="<?php echo htmlspecialchars(getProductImageUrl($item["image"], "../")); ?>"
                            class="product-image"
                            alt="<?php echo htmlspecialchars($item["name"]); ?>"
                            loading="lazy"
                        >
                    </a>


                    <div class="product-info">

                        <div class="product-name">
                            <a href="product_details.php?id=<?php echo (int)$item["product_id"]; ?>" style="color: inherit; text-decoration: none;">
                                <?php
                                echo htmlspecialchars(
                                    $item["name"]
                                );
                                ?>
                            </a>
                        </div>

                        </div>

                        <div class="product-quantity">

                            Quantity:
                            <?php
                            echo (int)$item["quantity"];
                            ?>

                            ×

                            ₹<?php
                            echo number_format(
                                $item["price"],
                                2
                            );
                            ?>

                        </div>

                    </div>


                    <div class="product-price">

                        ₹<?php
                        echo number_format(
                            $item["price"] *
                            $item["quantity"],
                            2
                        );
                        ?>

                    </div>

                </div>

            <?php endforeach; ?>


            <!-- TOTAL -->

            <div class="total-box">

                <span class="total-label">
                    Order Total
                </span>

                <span class="total-value">

                    ₹<?php
                    echo number_format(
                        $order["total_amount"],
                        2
                    );
                    ?>

                </span>

            </div>

        </div>


        <!-- RIGHT -->

        <div>


            <!-- DELIVERY ADDRESS -->

            <div class="card">

                <h2 class="card-title">
                    Delivery Address
                </h2>

                <div class="address-box">

                    <div class="address-name">

                        <?php
                        echo htmlspecialchars(
                            $order["full_name"]
                        );
                        ?>

                    </div>

                    <div class="address-line">

                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $order["address"]
                            )
                        );
                        ?>

                        <br>

                        <?php
                        echo htmlspecialchars(
                            $order["city"]
                        );
                        ?>,

                        <?php
                        echo htmlspecialchars(
                            $order["state"]
                        );
                        ?>

                        -

                        <?php
                        echo htmlspecialchars(
                            $order["pincode"]
                        );
                        ?>

                        <br>

                        Phone:
                        <?php
                        echo htmlspecialchars(
                            $order["phone"]
                        );
                        ?>

                    </div>

                </div>

            </div>


            <!-- PAYMENT -->

            <div class="card payment-box">

                <h2 class="card-title">
                    Payment Information
                </h2>


                <div class="info-line">

                    <span class="info-label">
                        Method
                    </span>

                    <span class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $order["payment_method"]
                        );
                        ?>

                    </span>

                </div>


                <div class="info-line">

                    <span class="info-label">
                        Status
                    </span>

                    <span class="info-value">

                        <?php
                        echo ucfirst(
                            htmlspecialchars(
                                $order["payment_status"]
                            )
                        );
                        ?>

                    </span>

                </div>


                <div class="info-line">

                    <span class="info-label">
                        Order Status
                    </span>

                    <span class="info-value">

                        <?php
                        echo ucfirst(
                            htmlspecialchars(
                                $order["order_status"]
                            )
                        );
                        ?>

                    </span>

                </div>

            </div>

        </div>

    </div>


    <a href="orders.php" class="orders-btn">
        ← Back to My Orders
    </a>


</main>

</body>

</html>