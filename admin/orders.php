<?php

session_start();

require_once "../config/db.php";

/* =========================================================
   ADMIN SECURITY
   ========================================================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}


/* =========================================================
   UPDATE ORDER STATUS
   ========================================================= */

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $order_id = intval($_POST["order_id"] ?? 0);
    $new_status = $_POST["order_status"] ?? "";

    $allowed_statuses = [
        "placed",
        "confirmed",
        "shipped",
        "delivered",
        "cancelled"
    ];

    if (
        $order_id > 0 &&
        in_array($new_status, $allowed_statuses, true)
    ) {

        $stmt = $conn->prepare(
            "UPDATE orders
             SET order_status = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "si",
            $new_status,
            $order_id
        );

        if ($stmt->execute()) {

            $message = "Order status updated successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to update order status.";
            $message_type = "error";
        }

        $stmt->close();

    } else {

        $message = "Invalid order details.";
        $message_type = "error";
    }
}


/* =========================================================
   FETCH ORDERS
   ========================================================= */

$orders = [];

$sql = "
    SELECT
        o.id,
        o.user_id,
        o.total_amount,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.created_at,
        u.name AS customer_name,
        u.email AS customer_email
    FROM orders o
    INNER JOIN users u
        ON o.user_id = u.id
    ORDER BY o.id DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $orders[] = $row;
    }
}


/* =========================================================
   ORDER STATISTICS
   ========================================================= */

$total_orders = count($orders);

$placed_orders = 0;
$confirmed_orders = 0;
$shipped_orders = 0;
$delivered_orders = 0;
$cancelled_orders = 0;

$total_sales = 0;

foreach ($orders as $order) {

    $total_sales += (float)$order["total_amount"];

    switch ($order["order_status"]) {

        case "placed":
            $placed_orders++;
            break;

        case "confirmed":
            $confirmed_orders++;
            break;

        case "shipped":
            $shipped_orders++;
            break;

        case "delivered":
            $delivered_orders++;
            break;

        case "cancelled":
            $cancelled_orders++;
            break;
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Orders | QARTIV Admin</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f5f7fb;
            color: #18213c;
        }


        /* =====================================================
           PAGE
           ===================================================== */

        .orders-page {
            min-height: 100vh;
        }


        /* =====================================================
           TOP BAR
           ===================================================== */

        .topbar {

            height: 72px;

            background: #ffffff;

            border-bottom: 1px solid #e7eaf1;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 40px;

            position: sticky;

            top: 0;

            z-index: 10;
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .brand-mark {

            width: 40px;

            height: 40px;

            border-radius: 11px;

            background: #202b6b;

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 18px;

            font-weight: 900;
        }


        .brand-text strong {

            display: block;

            font-size: 17px;

            letter-spacing: 2px;

            color: #202b6b;
        }


        .brand-text span {

            display: block;

            margin-top: 2px;

            font-size: 11px;

            color: #9299aa;
        }


        .admin-area {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .admin-avatar {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            background: #eef0ff;

            color: #202b6b;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 800;
        }


        .admin-name {

            font-size: 13px;

            font-weight: 700;

            color: #30384d;
        }


        /* =====================================================
           MAIN
           ===================================================== */

        .main {

            width: 100%;

            max-width: 1450px;

            margin: 0 auto;

            padding: 38px 40px 60px;
        }


        /* =====================================================
           HEADER
           ===================================================== */

        .page-header {

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 30px;
        }


        .header-small {

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 2px;

            font-weight: 800;

            color: #5965c8;

            margin-bottom: 7px;
        }


        .page-header h1 {

            font-size: 32px;

            margin-bottom: 7px;

            color: #18213c;
        }


        .page-header p {

            color: #81899b;

            font-size: 14px;
        }


        .back-btn {

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 11px 17px;

            border: 1px solid #dfe3ec;

            border-radius: 10px;

            background: #ffffff;

            color: #303b72;

            font-size: 13px;

            font-weight: 700;
        }


        .back-btn:hover {

            background: #f4f5ff;

            border-color: #5965c8;
        }


        /* =====================================================
           MESSAGE
           ===================================================== */

        .message {

            padding: 13px 16px;

            border-radius: 10px;

            margin-bottom: 25px;

            font-size: 13px;

            font-weight: 700;
        }


        .message.success {

            background: #eaf8f0;

            color: #198754;
        }


        .message.error {

            background: #fff0f0;

            color: #d43c3c;
        }


        /* =====================================================
           STATS
           ===================================================== */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 30px;
        }


        .stat-card {

            background: #ffffff;

            border: 1px solid #e8ebf2;

            border-radius: 15px;

            padding: 21px;

            box-shadow:
                0 5px 20px
                rgba(25, 35, 70, 0.04);
        }


        .stat-label {

            color: #81899b;

            font-size: 12px;

            font-weight: 700;

            margin-bottom: 10px;
        }


        .stat-number {

            font-size: 27px;

            font-weight: 800;

            color: #18213c;
        }


        .stat-sub {

            margin-top: 7px;

            font-size: 11px;

            color: #a0a7b6;
        }


        /* =====================================================
           ORDERS PANEL
           ===================================================== */

        .orders-panel {

            background: #ffffff;

            border: 1px solid #e8ebf2;

            border-radius: 17px;

            overflow: hidden;

            box-shadow:
                0 5px 20px
                rgba(25, 35, 70, 0.04);
        }


        .panel-header {

            padding: 22px 25px;

            border-bottom: 1px solid #edf0f5;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .panel-header h2 {

            font-size: 18px;

            color: #18213c;
        }


        .panel-header span {

            font-size: 12px;

            color: #9299aa;
        }


        .table-wrapper {

            width: 100%;

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1000px;
        }


        th {

            text-align: left;

            padding: 15px 20px;

            background: #fafbfe;

            color: #7d8597;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .7px;

            white-space: nowrap;
        }


        td {

            padding: 17px 20px;

            border-top: 1px solid #eef0f4;

            font-size: 13px;

            vertical-align: middle;
        }


        .order-number {

            font-weight: 800;

            color: #202b6b;
        }


        .customer {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .customer-avatar {

            width: 36px;

            height: 36px;

            border-radius: 50%;

            background: #eef0ff;

            color: #202b6b;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 800;

            font-size: 12px;
        }


        .customer-info strong {

            display: block;

            font-size: 13px;

            color: #30384d;
        }


        .customer-info small {

            display: block;

            margin-top: 3px;

            color: #9aa1b0;

            font-size: 10px;
        }


        .amount {

            font-weight: 800;

            color: #18213c;
        }


        .payment-method {

            color: #626b7d;

            font-size: 12px;

            font-weight: 700;
        }


        /* =====================================================
           STATUS
           ===================================================== */

        .status {

            display: inline-flex;

            align-items: center;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: .5px;
        }


        .status.placed {

            background: #fff5df;

            color: #b77900;
        }


        .status.confirmed {

            background: #eef0ff;

            color: #4d5ac4;
        }


        .status.shipped {

            background: #eaf4ff;

            color: #2674b8;
        }


        .status.delivered {

            background: #eaf8f0;

            color: #198754;
        }


        .status.cancelled {

            background: #fff0f0;

            color: #d43c3c;
        }


        .payment-paid {

            color: #198754;

            font-weight: 800;

            font-size: 11px;
        }


        .payment-pending {

            color: #b77900;

            font-weight: 800;

            font-size: 11px;
        }


        .payment-failed {

            color: #d43c3c;

            font-weight: 800;

            font-size: 11px;
        }


        /* =====================================================
           ACTIONS
           ===================================================== */

        .action-area {

            display: flex;

            align-items: center;

            gap: 8px;
        }


        .status-form select {

            height: 36px;

            padding: 0 9px;

            border: 1px solid #dfe3ec;

            border-radius: 8px;

            background: #ffffff;

            color: #30384d;

            font-size: 11px;

            outline: none;

            cursor: pointer;
        }


        .status-form select:focus {

            border-color: #5965c8;
        }


        .update-btn {

            height: 36px;

            padding: 0 11px;

            border: none;

            border-radius: 8px;

            background: #202b6b;

            color: #ffffff;

            font-size: 11px;

            font-weight: 800;

            cursor: pointer;
        }


        .update-btn:hover {

            background: #3946a8;
        }


        .view-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            height: 36px;

            padding: 0 11px;

            border-radius: 8px;

            border: 1px solid #dfe3ec;

            text-decoration: none;

            color: #303b72;

            font-size: 11px;

            font-weight: 800;
        }


        .view-btn:hover {

            background: #f4f5ff;

            border-color: #5965c8;
        }


        /* =====================================================
           EMPTY
           ===================================================== */

        .empty {

            text-align: center;

            padding: 70px 20px;

            color: #8d95a7;
        }


        .empty-icon {

            font-size: 45px;

            margin-bottom: 15px;
        }


        .empty h3 {

            color: #30384d;

            font-size: 18px;

            margin-bottom: 7px;
        }


        .empty p {

            font-size: 13px;
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 1000px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 700px) {

            .topbar {

                padding: 0 18px;

            }


            .admin-name {

                display: none;

            }


            .main {

                padding: 28px 18px 50px;

            }


            .page-header {

                align-items: flex-start;

                flex-direction: column;

            }


            .page-header h1 {

                font-size: 27px;

            }


            .stats {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<div class="orders-page">


    <!-- =====================================================
         TOP BAR
         ===================================================== -->

    <header class="topbar">


        <div class="brand">

            <div class="brand-mark">
                Q
            </div>


            <div class="brand-text">

                <strong>
                    QARTIV
                </strong>

                <span>
                    Smart E-Commerce Platform
                </span>

            </div>

        </div>


        <div class="admin-area">

            <div class="admin-avatar">
                A
            </div>

            <div class="admin-name">
                Administrator
            </div>

        </div>


    </header>



    <!-- =====================================================
         MAIN
         ===================================================== -->

    <main class="main">


        <!-- PAGE HEADER -->

        <div class="page-header">


            <div>

                <div class="header-small">
                    Admin Panel
                </div>

                <h1>
                    Orders
                </h1>

                <p>
                    Manage customer orders and update delivery status.
                </p>

            </div>


            <a
                href="dashboard.php"
                class="back-btn"
            >
                ← Dashboard
            </a>


        </div>



        <!-- MESSAGE -->

        <?php if (!empty($message)): ?>

            <div
                class="message <?php echo $message_type; ?>"
            >

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>



        <!-- =================================================
             STATISTICS
             ================================================= -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    Total Orders
                </div>

                <div class="stat-number">
                    <?php echo $total_orders; ?>
                </div>

                <div class="stat-sub">
                    All customer orders
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Pending Orders
                </div>

                <div class="stat-number">
                    <?php echo $placed_orders; ?>
                </div>

                <div class="stat-sub">
                    Waiting for confirmation
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Delivered
                </div>

                <div class="stat-number">
                    <?php echo $delivered_orders; ?>
                </div>

                <div class="stat-sub">
                    Successfully delivered
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Total Sales
                </div>

                <div class="stat-number">
                    ₹<?php echo number_format($total_sales, 2); ?>
                </div>

                <div class="stat-sub">
                    Order value
                </div>

            </div>


        </div>



        <!-- =================================================
             ORDERS
             ================================================= -->

        <section class="orders-panel">


            <div class="panel-header">

                <h2>
                    All Orders
                </h2>

                <span>
                    <?php echo $total_orders; ?> orders
                </span>

            </div>


            <?php if (!empty($orders)): ?>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Order
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Payment
                                </th>

                                <th>
                                    Payment Status
                                </th>

                                <th>
                                    Order Status
                                </th>

                                <th>
                                    Update
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($orders as $order): ?>


                            <tr>


                                <!-- ORDER -->

                                <td>

                                    <div class="order-number">

                                        #QRT-<?php
                                        echo str_pad(
                                            $order["id"],
                                            5,
                                            "0",
                                            STR_PAD_LEFT
                                        );
                                        ?>

                                    </div>

                                    <small style="
                                        display:block;
                                        margin-top:5px;
                                        color:#9aa1b0;
                                        font-size:10px;
                                    ">

                                        <?php
                                        echo date(
                                            "d M Y, h:i A",
                                            strtotime($order["created_at"])
                                        );
                                        ?>

                                    </small>

                                </td>



                                <!-- CUSTOMER -->

                                <td>

                                    <div class="customer">


                                        <div class="customer-avatar">

                                            <?php
                                            echo strtoupper(
                                                substr(
                                                    $order["customer_name"],
                                                    0,
                                                    1
                                                )
                                            );
                                            ?>

                                        </div>


                                        <div class="customer-info">

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $order["customer_name"]
                                                );
                                                ?>
                                            </strong>

                                            <small>
                                                <?php
                                                echo htmlspecialchars(
                                                    $order["customer_email"]
                                                );
                                                ?>
                                            </small>

                                        </div>


                                    </div>

                                </td>



                                <!-- AMOUNT -->

                                <td>

                                    <span class="amount">

                                        ₹<?php
                                        echo number_format(
                                            $order["total_amount"],
                                            2
                                        );
                                        ?>

                                    </span>

                                </td>



                                <!-- PAYMENT METHOD -->

                                <td>

                                    <span class="payment-method">

                                        <?php
                                        echo htmlspecialchars(
                                            $order["payment_method"]
                                        );
                                        ?>

                                    </span>

                                </td>



                                <!-- PAYMENT STATUS -->

                                <td>

                                    <?php

                                    $payment_class =
                                        "payment-" .
                                        $order["payment_status"];

                                    ?>

                                    <span
                                        class="<?php echo $payment_class; ?>"
                                    >

                                        <?php
                                        echo ucfirst(
                                            $order["payment_status"]
                                        );
                                        ?>

                                    </span>

                                </td>



                                <!-- ORDER STATUS -->

                                <td>

                                    <span
                                        class="status
                                        <?php
                                        echo htmlspecialchars(
                                            $order["order_status"]
                                        );
                                        ?>"
                                    >

                                        <?php
                                        echo ucfirst(
                                            $order["order_status"]
                                        );
                                        ?>

                                    </span>

                                </td>



                                <!-- UPDATE -->

                                <td>

                                    <div class="action-area">


                                        <form
                                            method="POST"
                                            class="status-form"
                                        >


                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?php
                                                echo $order["id"];
                                                ?>"
                                            >


                                            <select
                                                name="order_status"
                                            >

                                                <option
                                                    value="placed"
                                                    <?php
                                                    echo $order["order_status"] === "placed"
                                                        ? "selected"
                                                        : "";
                                                    ?>
                                                >
                                                    Placed
                                                </option>


                                                <option
                                                    value="confirmed"
                                                    <?php
                                                    echo $order["order_status"] === "confirmed"
                                                        ? "selected"
                                                        : "";
                                                    ?>
                                                >
                                                    Confirmed
                                                </option>


                                                <option
                                                    value="shipped"
                                                    <?php
                                                    echo $order["order_status"] === "shipped"
                                                        ? "selected"
                                                        : "";
                                                    ?>
                                                >
                                                    Shipped
                                                </option>


                                                <option
                                                    value="delivered"
                                                    <?php
                                                    echo $order["order_status"] === "delivered"
                                                        ? "selected"
                                                        : "";
                                                    ?>
                                                >
                                                    Delivered
                                                </option>


                                                <option
                                                    value="cancelled"
                                                    <?php
                                                    echo $order["order_status"] === "cancelled"
                                                        ? "selected"
                                                        : "";
                                                    ?>
                                                >
                                                    Cancelled
                                                </option>

                                            </select>


                                            <button
                                                type="submit"
                                                class="update-btn"
                                            >
                                                Update
                                            </button>


                                        </form>


                                        <a
                                            href="../customer/order_details.php?id=<?php echo $order["id"]; ?>"
                                            class="view-btn"
                                        >
                                            View
                                        </a>

                                        <a
                                            href="../customer/invoice.php?id=<?php echo $order["id"]; ?>"
                                            target="_blank"
                                            class="view-btn"
                                            title="View & Print Tax Invoice"
                                        >
                                            📄 Invoice
                                        </a>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-icon">
                        🧾
                    </div>

                    <h3>
                        No Orders Yet
                    </h3>

                    <p>
                        Customer orders will appear here.
                    </p>

                </div>


            <?php endif; ?>


        </section>


    </main>


</div>


</body>

</html>