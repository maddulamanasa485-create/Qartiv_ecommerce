<?php
session_start();
require_once "../config/db.php";

/* CUSTOMER LOGIN CHECK */
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "Customer";

/* CANCEL ORDER ACTION */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_order"])) {
    $cancelId = (int)($_POST["order_id"] ?? 0);
    if ($cancelId > 0) {
        $cStmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ? AND user_id = ? AND order_status = 'placed'");
        $cStmt->bind_param("ii", $cancelId, $user_id);
        if ($cStmt->execute() && $cStmt->affected_rows > 0) {
            // Restore inventory
            $itemQ = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = " . $cancelId);
            while ($it = $itemQ->fetch_assoc()) {
                $conn->query("UPDATE products SET stock = stock + " . (int)$it['quantity'] . " WHERE id = " . (int)$it['product_id']);
            }
            $_SESSION['order_msg'] = "Order #QRT-" . str_pad($cancelId, 5, "0", STR_PAD_LEFT) . " was successfully cancelled.";
            $_SESSION['order_msg_type'] = "success";
        } else {
            $_SESSION['order_msg'] = "Unable to cancel order. Only orders in 'placed' status can be cancelled.";
            $_SESSION['order_msg_type'] = "error";
        }
        $cStmt->close();
    }
    header("Location: orders.php");
    exit;
}

$orderMsg = $_SESSION['order_msg'] ?? '';
$orderMsgType = $_SESSION['order_msg_type'] ?? '';
unset($_SESSION['order_msg'], $_SESSION['order_msg_type']);

/* FETCH ORDERS */
$stmt = $conn->prepare("
    SELECT id, total_amount, payment_method, payment_status,
           order_status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Orders | QARTIV</title>

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

        .orders-header {
            height: 76px;
            background: #ffffff;
            border-bottom: 1px solid #e8ebf2;

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
            gap: 25px;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #555d70;
        }

        .logout-btn {
            padding: 10px 18px;
            border-radius: 9px;
            background: #eef0ff;
            color: #3946a8;
            font-weight: 600;
            font-size: 13px;
        }

        .logout-btn:hover {
            background: #3946a8;
            color: white;
        }

        /* MAIN */

        .orders-container {
            width: 88%;
            max-width: 1200px;
            margin: 40px auto;
        }

        .top-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: #18213c;
        }

        .page-description {
            margin-top: 7px;
            color: #747c8f;
            font-size: 14px;
        }

        .continue-shopping {
            background: #202b6b;
            color: white;
            padding: 13px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .continue-shopping:hover {
            background: #3946a8;
        }

        /* ORDER CARD */

        .order-card {
            background: white;
            border: 1px solid #e7eaf1;
            border-radius: 18px;
            margin-bottom: 18px;
            padding: 24px;

            box-shadow: 0 7px 25px rgba(35, 45, 80, 0.05);
        }

        .order-top {
            display: flex;
            align-items: center;
            justify-content: space-between;

            padding-bottom: 20px;
            border-bottom: 1px solid #edf0f5;
        }

        .order-number {
            font-size: 18px;
            font-weight: 800;
            color: #202b6b;
        }

        .order-date {
            margin-top: 6px;
            font-size: 13px;
            color: #858da0;
        }

        /* STATUS */

        .status {
            padding: 8px 15px;
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

        /* ORDER DETAILS */

        .order-middle {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;

            padding: 20px 0;
        }

        .detail-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #9299aa;
            margin-bottom: 6px;
        }

        .detail-value {
            font-size: 15px;
            font-weight: 700;
            color: #252d43;
        }

        .amount {
            color: #202b6b;
            font-size: 18px;
        }

        /* BOTTOM */

        .order-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;

            padding-top: 18px;
            border-top: 1px solid #edf0f5;
        }

        .payment-info {
            font-size: 13px;
            color: #737b8e;
        }

        .view-btn {
            padding: 10px 18px;
            border: 1px solid #dfe3ed;
            border-radius: 9px;
            color: #303b72;
            font-size: 13px;
            font-weight: 700;
            background: white;
        }

        .view-btn:hover {
            background: #202b6b;
            color: white;
        }

        /* EMPTY */

        .empty-box {
            background: white;
            border-radius: 20px;
            border: 1px solid #e7eaf1;
            text-align: center;
            padding: 80px 20px;
        }

        .empty-icon {
            font-size: 55px;
            margin-bottom: 18px;
        }

        .empty-box h2 {
            font-size: 23px;
            margin-bottom: 8px;
        }

        .empty-box p {
            color: #7d8597;
            font-size: 14px;
            margin-bottom: 25px;
        }

        /* RESPONSIVE */

        @media (max-width: 700px) {

            .orders-header {
                padding: 0 20px;
            }

            .user-name {
                display: none;
            }

            .orders-container {
                width: 92%;
            }

            .top-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 18px;
            }

            .page-title {
                font-size: 27px;
            }

            .order-top {
                align-items: flex-start;
                gap: 15px;
                flex-direction: column;
            }

            .order-middle {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .order-bottom {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

        }

    </style>
</head>

<body>

<!-- HEADER -->

<header class="orders-header">

    <a href="dashboard.php" class="brand">
        QARTIV
    </a>

    <div class="header-right">

        <span class="user-name">
            Hi, <?php echo htmlspecialchars($user_name); ?>
        </span>

        <a href="../auth/logout.php" class="logout-btn">
            Logout
        </a>

    </div>

</header>


<!-- MAIN -->

<main class="orders-container">

    <?php if (!empty($orderMsg)): ?>
        <div style="padding:14px 20px;border-radius:12px;margin-bottom:24px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;background:<?php echo $orderMsgType === 'success' ? '#ecfdf5' : '#fef2f2'; ?>;color:<?php echo $orderMsgType === 'success' ? '#065f46' : '#991b1b'; ?>;border:1px solid <?php echo $orderMsgType === 'success' ? '#a7f3d0' : '#fecaca'; ?>;">
            <span><?php echo $orderMsgType === 'success' ? '✓' : '⚠'; ?></span>
            <?php echo htmlspecialchars($orderMsg); ?>
        </div>
    <?php endif; ?>

    <div class="top-section">

        <div>

            <h1 class="page-title">
                My Orders
            </h1>

            <p class="page-description">
                Track and manage all your Qartiv orders.
            </p>

        </div>

        <a href="products.php" class="continue-shopping">
            ← Continue Shopping
        </a>

    </div>


    <?php if (count($orders) > 0): ?>

        <?php foreach ($orders as $order): ?>

            <?php

            $status = strtolower($order["order_status"]);

            $status_class = "status-" . $status;

            $date = date(
                "d M Y, h:i A",
                strtotime($order["created_at"])
            );

            ?>

            <div class="order-card">

                <!-- TOP -->

                <div class="order-top">

                    <div>

                        <div class="order-number">

                            Order #QRT-<?php
                            echo str_pad(
                                $order["id"],
                                5,
                                "0",
                                STR_PAD_LEFT
                            );
                            ?>

                        </div>

                        <div class="order-date">

                            Placed on <?php echo $date; ?>

                        </div>

                    </div>


                    <span class="status <?php echo $status_class; ?>">

                        <?php
                        echo htmlspecialchars(
                            $order["order_status"]
                        );
                        ?>

                    </span>

                </div>


                <!-- MIDDLE -->

                <div class="order-middle">

                    <div>

                        <div class="detail-label">
                            Total Amount
                        </div>

                        <div class="detail-value amount">

                            ₹<?php
                            echo number_format(
                                $order["total_amount"],
                                2
                            );
                            ?>

                        </div>

                    </div>


                    <div>

                        <div class="detail-label">
                            Payment Method
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $order["payment_method"]
                            );
                            ?>

                        </div>

                    </div>


                    <div>

                        <div class="detail-label">
                            Payment Status
                        </div>

                        <div class="detail-value">

                            <?php
                            echo ucfirst(
                                htmlspecialchars(
                                    $order["payment_status"]
                                )
                            );
                            ?>

                        </div>

                    </div>

                </div>


                <!-- BOTTOM -->

                <div class="order-bottom">

                    <div class="payment-info">

                        Payment:
                        <?php
                        echo ucfirst(
                            htmlspecialchars(
                                $order["payment_status"]
                            )
                        );
                        ?>

                    </div>


                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <a href="invoice.php?id=<?php echo $order["id"]; ?>" target="_blank" style="padding:8px 14px;border:1px solid #d4af37;background:#fff;color:#1a1e36;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            📄 Invoice
                        </a>
                        <a
                            href="order_details.php?id=<?php echo $order["id"]; ?>"
                            class="view-btn"
                        >
                            View Details →
                        </a>
                        <?php if ($status === 'placed'): ?>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="cancel_order" style="padding:8px 14px;border:1px solid #fca5a5;background:#fef2f2;color:#b91c1c;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
                                    Cancel Order
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty-box">

            <div class="empty-icon">
                🛍️
            </div>

            <h2>
                No Orders Yet
            </h2>

            <p>
                You haven't placed any orders yet.
                Start shopping and your orders will appear here.
            </p>

            <a
                href="products.php"
                class="continue-shopping"
            >
                Start Shopping →
            </a>

        </div>

    <?php endif; ?>

</main>

</body>
</html>