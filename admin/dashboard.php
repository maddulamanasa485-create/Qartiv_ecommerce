<?php

session_start();

require_once "../config/db.php";

/* =========================================================
   ADMIN SECURITY
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../customer/dashboard.php");
    exit;
}

$adminName = $_SESSION["user_name"] ?? "Admin";

/* =========================================================
   DATABASE STATISTICS
========================================================= */

$totalCustomers = 0;
$totalProducts = 0;
$totalCategories = 0;
$totalOrders = 0;
$pendingOrders = 0;
$deliveredOrders = 0;
$totalSales = 0;

/* Customers */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'customer'"
);

if ($result) {
    $row = $result->fetch_assoc();
    $totalCustomers = (int)$row["total"];
}

/* Products */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE status = 'active'"
);

if ($result) {
    $row = $result->fetch_assoc();
    $totalProducts = (int)$row["total"];
}

/* Categories */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM categories
     WHERE status = 'active'"
);

if ($result) {
    $row = $result->fetch_assoc();
    $totalCategories = (int)$row["total"];
}

/* Orders */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM orders"
);

if ($result) {
    $row = $result->fetch_assoc();
    $totalOrders = (int)$row["total"];
}

/* Pending / active orders */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE order_status IN ('placed','confirmed','shipped')"
);

if ($result) {
    $row = $result->fetch_assoc();
    $pendingOrders = (int)$row["total"];
}

/* Delivered */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE order_status = 'delivered'"
);

if ($result) {
    $row = $result->fetch_assoc();
    $deliveredOrders = (int)$row["total"];
}

/* Sales */
$result = $conn->query(
    "SELECT COALESCE(SUM(total_amount),0) AS total
     FROM orders
     WHERE payment_status = 'paid'
        OR order_status = 'delivered'"
);

if ($result) {
    $row = $result->fetch_assoc();
    $totalSales = (float)$row["total"];
}

/* =========================================================
   RECENT ORDERS
========================================================= */

$recentOrders = [];

$result = $conn->query(
    "SELECT
        orders.id,
        orders.total_amount,
        orders.payment_method,
        orders.payment_status,
        orders.order_status,
        orders.created_at,
        users.name AS customer_name
     FROM orders
     INNER JOIN users
        ON users.id = orders.user_id
     ORDER BY orders.id DESC
     LIMIT 6"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

/* =========================================================
   LOW STOCK PRODUCTS
========================================================= */

$lowStockProducts = [];

$result = $conn->query(
    "SELECT id, name, stock, price
     FROM products
     WHERE status = 'active'
       AND stock <= 5
     ORDER BY stock ASC
     LIMIT 5"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lowStockProducts[] = $row;
    }
}

/* =========================================================
   CATEGORY SNAPSHOT
========================================================= */

$categoryData = [];

$result = $conn->query(
    "SELECT
        categories.name,
        COUNT(products.id) AS product_count
     FROM categories
     LEFT JOIN products
        ON products.category_id = categories.id
        AND products.status = 'active'
     WHERE categories.status = 'active'
     GROUP BY categories.id, categories.name
     ORDER BY product_count DESC"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categoryData[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard | QARTIV</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f6fa;
    color: #171923;
}

a {
    text-decoration: none;
    color: inherit;
}

/* =========================================================
   LAYOUT
========================================================= */

.admin-layout {
    min-height: 100vh;
    display: flex;
}

/* =========================================================
   SIDEBAR
========================================================= */

.admin-sidebar {
    width: 250px;
    min-height: 100vh;

    background: #11131c;
    color: #ffffff;

    padding: 28px 18px;

    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;

    display: flex;
    flex-direction: column;
}

.admin-brand {
    padding: 0 12px 30px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.admin-brand small {
    display: block;
    color: #8e94a7;
    font-size: 10px;
    letter-spacing: 2px;
    margin-bottom: 5px;
}

.admin-brand strong {
    font-size: 24px;
    letter-spacing: 2px;
}

.admin-nav {
    margin-top: 28px;
}

.nav-title {
    color: #747b91;
    font-size: 10px;
    letter-spacing: 1.5px;
    padding: 0 12px;
    margin-bottom: 10px;
}

.admin-nav a {
    display: flex;
    align-items: center;
    gap: 12px;

    padding: 12px 13px;
    margin-bottom: 5px;

    border-radius: 10px;

    color: #aeb3c3;

    font-size: 13px;
    font-weight: 600;

    transition: 0.2s;
}

.admin-nav a:hover,
.admin-nav a.active {
    background: #252938;
    color: #ffffff;
}

.nav-icon {
    width: 28px;
    height: 28px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: rgba(255,255,255,0.05);
}

.admin-sidebar-bottom {
    margin-top: auto;
}

.admin-user-box {
    padding: 14px;
    margin-bottom: 10px;

    background: #191c27;
    border-radius: 12px;
}

.admin-user-box small {
    display: block;
    color: #73798b;
    font-size: 10px;
    margin-bottom: 5px;
}

.admin-user-box strong {
    font-size: 13px;
}

.logout-link {
    display: flex !important;
    align-items: center;
    justify-content: center;

    background: #252938 !important;
}

/* =========================================================
   MAIN
========================================================= */

.admin-main {
    margin-left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;

    padding: 30px 34px;
}

/* =========================================================
   TOP BAR
========================================================= */

.admin-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 30px;
}

.admin-topbar small {
    color: #858b9d;
    font-size: 12px;
}

.admin-topbar h1 {
    font-size: 28px;
    margin-top: 5px;
}

.admin-top-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-avatar {
    width: 42px;
    height: 42px;

    border-radius: 12px;

    background: #1b1e2a;
    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 700;
}

/* =========================================================
   HERO
========================================================= */

.admin-hero {
    background: #171a25;
    color: white;

    border-radius: 20px;

    padding: 30px;

    margin-bottom: 25px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    overflow: hidden;
}

.admin-hero small {
    color: #9ba1b2;
    letter-spacing: 1.5px;
    font-size: 10px;
}

.admin-hero h2 {
    font-size: 28px;
    margin: 9px 0;
}

.admin-hero p {
    color: #a9aebc;
    font-size: 13px;
    max-width: 580px;
    line-height: 1.6;
}

.hero-mark {
    width: 100px;
    height: 100px;

    border-radius: 28px;

    background: rgba(255,255,255,0.06);

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 44px;
    font-weight: 800;
}

/* =========================================================
   STAT CARDS
========================================================= */

.stats-grid {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 16px;

    margin-bottom: 25px;
}

.stat-card {
    background: white;

    border: 1px solid #e8e9ef;

    border-radius: 16px;

    padding: 20px;

    box-shadow:
        0 5px 20px rgba(20,25,40,0.04);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 18px;
}

.stat-icon {
    width: 40px;
    height: 40px;

    border-radius: 11px;

    background: #f0f1f6;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 18px;
}

.stat-arrow {
    color: #a1a6b5;
}

.stat-number {
    font-size: 27px;
    font-weight: 800;
}

.stat-label {
    color: #777d8f;
    font-size: 12px;
    margin-top: 4px;
}

/* =========================================================
   CONTENT GRID
========================================================= */

.content-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.65fr)
        minmax(300px, 1fr);

    gap: 20px;

    margin-bottom: 20px;
}

.panel {
    background: white;

    border: 1px solid #e8e9ef;

    border-radius: 17px;

    padding: 22px;

    box-shadow:
        0 5px 20px rgba(20,25,40,0.04);
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 20px;
}

.panel-header small {
    color: #8a90a1;
    font-size: 10px;
    letter-spacing: 1.5px;
}

.panel-header h3 {
    font-size: 18px;
    margin-top: 5px;
}

.panel-header a {
    color: #4f59bb;
    font-size: 12px;
    font-weight: 700;
}

/* =========================================================
   ORDER TABLE
========================================================= */

.order-table {
    width: 100%;
    border-collapse: collapse;
}

.order-table th {
    text-align: left;

    color: #969bab;

    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;

    padding: 10px 8px;

    border-bottom: 1px solid #eeeeF3;
}

.order-table td {
    padding: 14px 8px;

    border-bottom: 1px solid #f0f1f4;

    font-size: 12px;
}

.order-table tr:last-child td {
    border-bottom: 0;
}

.customer-name {
    font-weight: 700;
}

.order-date {
    display: block;
    color: #969bab;
    font-size: 10px;
    margin-top: 3px;
}

.status {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    background: #f0f1f5;

    font-size: 10px;
    font-weight: 700;

    text-transform: capitalize;
}

.status-delivered {
    background: #e9f7ef;
    color: #26794a;
}

.status-shipped {
    background: #eaf0ff;
    color: #4256a8;
}

.status-confirmed {
    background: #f1ecff;
    color: #684db3;
}

.status-placed {
    background: #fff4df;
    color: #9a6a19;
}

.status-cancelled {
    background: #fdeaea;
    color: #a13a3a;
}

.view-order {
    color: #505abb;
    font-weight: 700;
}

/* =========================================================
   LOW STOCK
========================================================= */

.stock-item {
    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 13px 0;

    border-bottom: 1px solid #f0f1f4;
}

.stock-item:last-child {
    border-bottom: 0;
}

.stock-name {
    font-size: 12px;
    font-weight: 700;
}

.stock-price {
    color: #8b90a0;
    font-size: 10px;
    margin-top: 3px;
}

.stock-count {
    padding: 6px 9px;

    border-radius: 8px;

    background: #fff0f0;

    color: #c24747;

    font-size: 10px;
    font-weight: 800;
}

/* =========================================================
   BOTTOM GRID
========================================================= */

.bottom-grid {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}

/* =========================================================
   CATEGORY
========================================================= */

.category-item {
    margin-bottom: 18px;
}

.category-top {
    display: flex;
    justify-content: space-between;

    margin-bottom: 7px;
}

.category-top span {
    font-size: 12px;
    font-weight: 700;
}

.category-top strong {
    font-size: 11px;
    color: #777d8e;
}

.category-bar {
    height: 7px;

    background: #eceef3;

    border-radius: 20px;

    overflow: hidden;
}

.category-fill {
    height: 100%;

    background: #1d2030;

    border-radius: 20px;
}

/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-grid {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;
}

.quick-card {
    border: 1px solid #e8e9ef;

    border-radius: 13px;

    padding: 16px;

    transition: 0.2s;
}

.quick-card:hover {
    background: #f7f7fa;
    transform: translateY(-2px);
}

.quick-card span {
    font-size: 19px;
}

.quick-card strong {
    display: block;

    font-size: 12px;

    margin-top: 9px;
}

.quick-card small {
    display: block;

    color: #8d92a1;

    font-size: 10px;

    margin-top: 3px;
}

/* =========================================================
   EMPTY
========================================================= */

.empty {
    padding: 30px 10px;

    text-align: center;

    color: #9499a8;

    font-size: 12px;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 1100px) {

    .stats-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .content-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 800px) {

    .admin-sidebar {
        position: relative;
        width: 100%;
        min-height: auto;
    }

    .admin-layout {
        display: block;
    }

    .admin-main {
        margin-left: 0;
        width: 100%;
        padding: 20px;
    }

    .admin-sidebar-bottom {
        margin-top: 20px;
    }

    .bottom-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 550px) {

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .admin-topbar {
        align-items: flex-start;
    }

    .admin-top-user {
        display: none;
    }

    .admin-hero {
        padding: 22px;
    }

    .hero-mark {
        display: none;
    }

    .order-table {
        min-width: 650px;
    }

    .panel {
        overflow-x: auto;
    }

}

</style>

</head>

<body>

<div class="admin-layout">

<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="admin-sidebar">

    <div class="admin-brand">

        <small>SMART COMMERCE</small>

        <strong>QARTIV</strong>

    </div>


    <nav class="admin-nav">

        <div class="nav-title">
            ADMIN PANEL
        </div>

        <a href="dashboard.php" class="active">
            <span class="nav-icon">⌂</span>
            Dashboard
        </a>

        <a href="products.php">
            <span class="nav-icon">◈</span>
            Products
        </a>

        <a href="categories.php">
            <span class="nav-icon">▦</span>
            Categories
        </a>

        <a href="orders.php">
            <span class="nav-icon">▣</span>
            Orders
        </a>

        <a href="customers.php">
            <span class="nav-icon">♙</span>
            Customers
        </a>

        <a href="coupons.php">
            <span class="nav-icon">%</span>
            Coupons
        </a>

        <div class="nav-title" style="margin-top:25px;">
            SYSTEM
        </div>

        <a href="../customer/dashboard.php">
            <span class="nav-icon">↗</span>
            Customer View
        </a>

    </nav>


    <div class="admin-sidebar-bottom">

        <div class="admin-user-box">

            <small>LOGGED IN AS</small>

            <strong>
                <?php echo htmlspecialchars($adminName); ?>
            </strong>

        </div>

        <a href="../auth/logout.php" class="admin-nav logout-link">
            <span class="nav-icon">↪</span>
            Logout
        </a>

    </div>

</aside>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="admin-main">


    <!-- TOP BAR -->

    <header class="admin-topbar">

        <div>

            <small>QARTIV / ADMIN SPACE</small>

            <h1>Dashboard</h1>

        </div>


        <div class="admin-top-user">

            <div>
                <strong>
                    <?php echo htmlspecialchars($adminName); ?>
                </strong>

                <small style="display:block;">
                    Administrator
                </small>
            </div>

            <div class="admin-avatar">
                <?php
                echo htmlspecialchars(
                    strtoupper(
                        substr($adminName, 0, 1)
                    )
                );
                ?>
            </div>

        </div>

    </header>


    <!-- HERO -->

    <section class="admin-hero">

        <div>

            <small>
                ✦ QARTIV SMART COMMERCE CONTROL CENTER
            </small>

            <h2>
                Welcome back, <?php echo htmlspecialchars($adminName); ?>.
            </h2>

            <p>
                Manage your products, customers, orders and
                store activity from one central dashboard.
            </p>

        </div>

        <div class="hero-mark">
            Q
        </div>

    </section>


    <!-- =================================================
         STATISTICS
    ================================================== -->

    <section class="stats-grid">


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon">
                    ♙
                </div>

                <span class="stat-arrow">
                    ↗
                </span>

            </div>

            <div class="stat-number">
                <?php echo $totalCustomers; ?>
            </div>

            <div class="stat-label">
                Total Customers
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon">
                    ◈
                </div>

                <span class="stat-arrow">
                    ↗
                </span>

            </div>

            <div class="stat-number">
                <?php echo $totalProducts; ?>
            </div>

            <div class="stat-label">
                Active Products
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon">
                    ▣
                </div>

                <span class="stat-arrow">
                    ↗
                </span>

            </div>

            <div class="stat-number">
                <?php echo $totalOrders; ?>
            </div>

            <div class="stat-label">
                Total Orders
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon">
                    ₹
                </div>

                <span class="stat-arrow">
                    ↗
                </span>

            </div>

            <div class="stat-number">
                ₹<?php echo number_format($totalSales, 0); ?>
            </div>

            <div class="stat-label">
                Store Sales
            </div>

        </div>


    </section>


    <!-- =================================================
         ORDERS + LOW STOCK
    ================================================== -->

    <section class="content-grid">


        <!-- RECENT ORDERS -->

        <div class="panel">

            <div class="panel-header">

                <div>

                    <small>ORDER ACTIVITY</small>

                    <h3>
                        Recent Orders
                    </h3>

                </div>

                <a href="orders.php">
                    View all →
                </a>

            </div>


            <?php if (!empty($recentOrders)): ?>

                <table class="order-table">

                    <thead>

                        <tr>

                            <th>Customer</th>

                            <th>Amount</th>

                            <th>Payment</th>

                            <th>Status</th>

                            <th></th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($recentOrders as $order): ?>

                        <tr>

                            <td>

                                <span class="customer-name">
                                    <?php
                                    echo htmlspecialchars(
                                        $order["customer_name"]
                                    );
                                    ?>
                                </span>

                                <span class="order-date">
                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $order["created_at"]
                                        )
                                    );
                                    ?>
                                </span>

                            </td>


                            <td>

                                ₹<?php
                                echo number_format(
                                    (float)$order["total_amount"],
                                    2
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $order["payment_method"]
                                );
                                ?>

                            </td>


                            <td>

                                <span class="status status-<?php
                                    echo htmlspecialchars(
                                        strtolower(
                                            $order["order_status"]
                                        )
                                    );
                                ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        $order["order_status"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <a
                                    href="../customer/order_details.php?id=<?php echo (int)$order["id"]; ?>"
                                    class="view-order">

                                    View

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty">
                    No orders available yet.
                </div>

            <?php endif; ?>

        </div>


        <!-- LOW STOCK -->

        <div class="panel">

            <div class="panel-header">

                <div>

                    <small>INVENTORY</small>

                    <h3>
                        Low Stock
                    </h3>

                </div>

                <a href="products.php">
                    Manage →
                </a>

            </div>


            <?php if (!empty($lowStockProducts)): ?>

                <?php foreach ($lowStockProducts as $product): ?>

                    <div class="stock-item">

                        <div>

                            <div class="stock-name">
                                <?php
                                echo htmlspecialchars(
                                    $product["name"]
                                );
                                ?>
                            </div>

                            <div class="stock-price">

                                ₹<?php
                                echo number_format(
                                    (float)$product["price"],
                                    2
                                );
                                ?>

                            </div>

                        </div>


                        <div class="stock-count">

                            <?php
                            echo (int)$product["stock"];
                            ?>
                            left

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    All products have healthy stock.
                </div>

            <?php endif; ?>

        </div>


    </section>


    <!-- =================================================
         CATEGORY + QUICK ACTIONS
    ================================================== -->

    <section class="bottom-grid">


        <!-- CATEGORY -->

        <div class="panel">

            <div class="panel-header">

                <div>

                    <small>STORE STRUCTURE</small>

                    <h3>
                        Category Snapshot
                    </h3>

                </div>

                <a href="categories.php">
                    Manage →
                </a>

            </div>


            <?php

            $maxProducts = 1;

            foreach ($categoryData as $category) {

                if ((int)$category["product_count"] > $maxProducts) {
                    $maxProducts =
                        (int)$category["product_count"];
                }

            }

            ?>


            <?php if (!empty($categoryData)): ?>

                <?php foreach ($categoryData as $category): ?>

                    <?php

                    $count =
                        (int)$category["product_count"];

                    $percentage =
                        ($count / $maxProducts) * 100;

                    ?>

                    <div class="category-item">

                        <div class="category-top">

                            <span>
                                <?php
                                echo htmlspecialchars(
                                    $category["name"]
                                );
                                ?>
                            </span>

                            <strong>
                                <?php echo $count; ?> products
                            </strong>

                        </div>

                        <div class="category-bar">

                            <div
                                class="category-fill"
                                style="width: <?php echo $percentage; ?>%;">
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    No categories available.
                </div>

            <?php endif; ?>

        </div>


        <!-- QUICK ACTIONS -->

        <div class="panel">

            <div class="panel-header">

                <div>

                    <small>SHORTCUTS</small>

                    <h3>
                        Quick Actions
                    </h3>

                </div>

            </div>


            <div class="quick-grid">


                <a href="products.php" class="quick-card">

                    <span>◈</span>

                    <strong>
                        Products
                    </strong>

                    <small>
                        Manage inventory
                    </small>

                </a>


                <a href="orders.php" class="quick-card">

                    <span>▣</span>

                    <strong>
                        Orders
                    </strong>

                    <small>
                        Manage purchases
                    </small>

                </a>


                <a href="categories.php" class="quick-card">

                    <span>▦</span>

                    <strong>
                        Categories
                    </strong>

                    <small>
                        Organize products
                    </small>

                </a>


                <a href="customers.php" class="quick-card">

                    <span>♙</span>

                    <strong>
                        Customers
                    </strong>

                    <small>
                        View customers
                    </small>

                </a>


            </div>

        </div>


    </section>


</main>

</div>

</body>

</html>