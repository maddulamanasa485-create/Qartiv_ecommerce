<?php
session_start();
require_once "../config/db.php";

/* =========================
   CUSTOMER ACCESS CHECK
========================= */
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../auth/login.php");
    exit;
}

$userId   = (int) $_SESSION["user_id"];
$userName = $_SESSION["user_name"] ?? "Customer";

/* =========================
   DASHBOARD STATISTICS
========================= */
$cartCount = 0;
$wishlistCount = 0;
$orderCount = 0;
$deliveredCount = 0;
$totalSpent = 0;

/* Cart items */
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(quantity), 0) AS total
     FROM cart
     WHERE user_id = ?"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $cartCount = (int) $row["total"];
    }

    $stmt->close();
}

/* Wishlist items */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM wishlist
     WHERE user_id = ?"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $wishlistCount = (int) $row["total"];
    }

    $stmt->close();
}

/* Total orders */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE user_id = ?"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $orderCount = (int) $row["total"];
    }

    $stmt->close();
}

/* Delivered orders */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE user_id = ?
     AND order_status = 'delivered'"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $deliveredCount = (int) $row["total"];
    }

    $stmt->close();
}

/* Total spent */
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(total_amount), 0) AS total
     FROM orders
     WHERE user_id = ?
     AND order_status != 'cancelled'"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $totalSpent = (float) $row["total"];
    }

    $stmt->close();
}

/* =========================
   RECENT ORDERS
========================= */
$recentOrders = [];

$stmt = $conn->prepare(
    "SELECT id, total_amount, order_status, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 3"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dashboard | QARTIV</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

</head>

<body class="qartiv-dashboard">

<div class="dashboard-layout">

    <!-- =========================
         SIDEBAR
    ========================== -->

    <aside class="dashboard-sidebar">

        <div class="sidebar-brand">

            <div class="brand-mark">
                Q
            </div>

            <div>
                <div class="brand-name">
                    QARTIV
                </div>

                <span>
                    SMART COMMERCE
                </span>
            </div>

        </div>


        <div class="sidebar-section-title">
            MAIN MENU
        </div>


        <nav class="dashboard-nav">

            <a href="dashboard.php"
               class="dashboard-nav-item active">

                <span class="nav-icon">⌂</span>

                <span>Dashboard</span>

            </a>


            <a href="products.php"
               class="dashboard-nav-item">

                <span class="nav-icon">◈</span>

                <span>Products</span>

            </a>


            <a href="cart.php"
               class="dashboard-nav-item">

                <span class="nav-icon">🛒</span>

                <span>My Cart</span>

                <?php if ($cartCount > 0): ?>

                    <b class="nav-count">
                        <?php echo $cartCount; ?>
                    </b>

                <?php endif; ?>

            </a>


            <a href="wishlist.php"
               class="dashboard-nav-item">

                <span class="nav-icon">♡</span>

                <span>Wishlist</span>

                <?php if ($wishlistCount > 0): ?>

                    <b class="nav-count">
                        <?php echo $wishlistCount; ?>
                    </b>

                <?php endif; ?>

            </a>


            <a href="orders.php"
               class="dashboard-nav-item">

                <span class="nav-icon">▣</span>

                <span>My Orders</span>

            </a>


            <div class="sidebar-section-title secondary">
                ACCOUNT
            </div>


            <a href="account.php"
               class="dashboard-nav-item">

                <span class="nav-icon">◎</span>

                <span>My Profile</span>

            </a>


            <a href="settings.php"
               class="dashboard-nav-item">

                <span class="nav-icon">⚙</span>

                <span>Settings</span>

            </a>

        </nav>


        <!-- SIDEBAR BOTTOM -->

        <div class="sidebar-bottom">

            <div class="sidebar-support">

                <div class="support-icon">
                    ✦
                </div>

                <div>

                    <strong>
                        Need help?
                    </strong>

                    <small>
                        We're here 24/7
                    </small>

                </div>

            </div>


            <a href="../auth/logout.php"
               class="dashboard-logout">

                <span>↪</span>

                <span>Logout</span>

            </a>

        </div>

    </aside>



    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="dashboard-main">


        <!-- TOP HEADER -->

        <header class="dashboard-header">

            <div class="header-left">

                <p class="header-mini">
                    QARTIV / CUSTOMER SPACE
                </p>

                <h2>
                    Welcome back,
                    <?php echo htmlspecialchars($userName); ?> 👋
                </h2>

            </div>


            <div class="header-right">

                <button class="notification-btn"
                        type="button">

                    <span>♢</span>

                    <i></i>

                </button>


                <div class="header-user">

                    <div class="header-avatar">

                        <?php
                        echo strtoupper(
                            substr($userName, 0, 1)
                        );
                        ?>

                    </div>

                    <div class="header-user-info">

                        <strong>
                            <?php echo htmlspecialchars($userName); ?>
                        </strong>

                        <span>
                            Customer
                        </span>

                    </div>

                    <span class="header-chevron">
                       ⌄
                    </span>

                </div>

            </div>

        </header>



        <!-- =========================
             HERO
        ========================== -->

        <section class="dashboard-hero">

            <div class="hero-content">

                <div class="hero-badge">
                    ✦ QARTIV SMART SHOPPING
                </div>

                <h1>
                    Find what you love.
                    <br>
                    <span>Shop it smarter.</span>
                </h1>

                <p>
                    Explore products made for your style,
                    discover new favourites and enjoy a
                    seamless shopping experience.
                </p>


                <div class="hero-actions">

                    <a href="products.php"
                       class="hero-primary-btn">

                        Explore Products

                        <span>→</span>

                    </a>


                    <a href="#"
                       class="hero-secondary-btn">

                        View Orders

                    </a>

                </div>

            </div>


            <div class="hero-floating-card">

                <div class="hero-floating-icon">
                    ✦
                </div>

                <div>

                    <strong>
                        Smart shopping
                    </strong>

                    <span>
                        Made simple for you
                    </span>

                </div>

            </div>

        </section>



        <!-- =========================
             STATS
        ========================== -->

        <section class="dashboard-stats">


            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon cart-icon">
                        🛒
                    </div>

                    <span class="stat-arrow">
                        ↗
                    </span>

                </div>

                <div class="stat-number">
                    <?php echo $cartCount; ?>
                </div>

                <div class="stat-label">
                    Cart Items
                </div>

                <div class="stat-bottom">
                    Ready for checkout
                </div>

            </div>



            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon wish-icon">
                        ♡
                    </div>

                    <span class="stat-arrow">
                        ↗
                    </span>

                </div>

                <div class="stat-number">
                    <?php echo $wishlistCount; ?>
                </div>

                <div class="stat-label">
                    Wishlist
                </div>

                <div class="stat-bottom">
                    Saved favourites
                </div>

            </div>



            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon order-icon">
                        ▣
                    </div>

                    <span class="stat-arrow">
                        ↗
                    </span>

                </div>

                <div class="stat-number">
                    <?php echo $orderCount; ?>
                </div>

                <div class="stat-label">
                    Total Orders
                </div>

                <div class="stat-bottom">
                    Your shopping history
                </div>

            </div>



            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon delivery-icon">
                        ✓
                    </div>

                    <span class="stat-arrow">
                        ↗
                    </span>

                </div>

                <div class="stat-number">
                    <?php echo $deliveredCount; ?>
                </div>

                <div class="stat-label">
                    Delivered
                </div>

                <div class="stat-bottom">
                    Successfully completed
                </div>

            </div>

        </section>



        <!-- =========================
             MAIN DASHBOARD GRID
        ========================== -->

        <section class="dashboard-content-grid">


            <!-- QUICK ACTIONS -->

            <div class="dashboard-panel quick-panel">

                <div class="panel-heading">

                    <div>

                        <span>
                            SHORTCUTS
                        </span>

                        <h3>
                            Quick access
                        </h3>

                    </div>

                    <span class="panel-dot">
                        •••
                    </span>

                </div>


                <div class="quick-grid">


                    <a href="#"
                       class="quick-card">

                        <div class="quick-card-icon">
                            ◈
                        </div>

                        <div>

                            <strong>
                                Browse Products
                            </strong>

                            <span>
                                Discover something new
                            </span>

                        </div>

                        <b>→</b>

                    </a>



                    <a href="#"
                       class="quick-card">

                        <div class="quick-card-icon">
                            🛒
                        </div>

                        <div>

                            <strong>
                                My Cart
                            </strong>

                            <span>
                                Review your selected items
                            </span>

                        </div>

                        <b>→</b>

                    </a>



                    <a href="#"
                       class="quick-card">

                        <div class="quick-card-icon">
                            ♡
                        </div>

                        <div>

                            <strong>
                                Wishlist
                            </strong>

                            <span>
                                Your favourite products
                            </span>

                        </div>

                        <b>→</b>

                    </a>



                    <a href="#"
                       class="quick-card">

                        <div class="quick-card-icon">
                            ▣
                        </div>

                        <div>

                            <strong>
                                My Orders
                            </strong>

                            <span>
                                Track your purchases
                            </span>

                        </div>

                        <b>→</b>

                    </a>

                </div>

            </div>



            <!-- SHOPPING SNAPSHOT -->

            <div class="dashboard-panel snapshot-panel">

                <div class="panel-heading">

                    <div>

                        <span>
                            YOUR ACTIVITY
                        </span>

                        <h3>
                            Shopping snapshot
                        </h3>

                    </div>

                </div>


                <div class="snapshot-total">

                    <span>
                        Total spent
                    </span>

                    <strong>
                        ₹<?php echo number_format($totalSpent, 2); ?>
                    </strong>

                </div>


                <div class="snapshot-line">

                    <div>

                        <span>
                            Orders placed
                        </span>

                        <strong>
                            <?php echo $orderCount; ?>
                        </strong>

                    </div>

                </div>


                <div class="snapshot-line">

                    <div>

                        <span>
                            Successfully delivered
                        </span>

                        <strong>
                            <?php echo $deliveredCount; ?>
                        </strong>

                    </div>

                </div>


                <div class="snapshot-progress">

                    <?php
                    $deliveryPercent = 0;

                    if ($orderCount > 0) {
                        $deliveryPercent =
                            min(
                                100,
                                ($deliveredCount / $orderCount) * 100
                            );
                    }
                    ?>

                    <div class="progress-label">

                        <span>
                            Delivery progress
                        </span>

                        <strong>
                            <?php
                            echo round($deliveryPercent);
                            ?>%
                        </strong>

                    </div>

                    <div class="progress-track">

                        <div
                            class="progress-fill"
                            style="width: <?php echo $deliveryPercent; ?>%;">
                        </div>

                    </div>

                </div>

            </div>

        </section>



        <!-- =========================
             RECENT ORDERS
        ========================== -->

        <section class="dashboard-panel recent-orders-panel">

            <div class="panel-heading">

                <div>

                    <span>
                        ORDER ACTIVITY
                    </span>

                    <h3>
                        Recent orders
                    </h3>

                </div>

                <a href="orders.php">
                    View all →
                </a>

            </div>


            <?php if (!empty($recentOrders)): ?>

                <div class="orders-list">

                    <?php foreach ($recentOrders as $order): ?>

                        <?php

                        $status = strtolower(
                            $order["order_status"]
                        );

                        $statusClass =
                            "status-" . $status;

                        ?>

                        <div class="order-row">

                            <div class="order-info">

                                <div class="order-symbol">
                                    #
                                </div>

                                <div>

                                    <strong>
                                        Order #<?php
                                        echo (int)$order["id"];
                                        ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $order["created_at"]
                                            )
                                        );
                                        ?>
                                    </span>

                                </div>

                            </div>


                            <div class="order-price">

                                ₹<?php
                                echo number_format(
                                    (float)$order["total_amount"],
                                    2
                                );
                                ?>

                            </div>


                            <span class="order-status <?php
                                echo $statusClass;
                            ?>">

                                <?php
                                echo ucfirst($status);
                                ?>

                            </span>


                            <a href="order_details.php?id=<?php echo (int)$order["id"]; ?>"
                               class="order-view">

                                View →

                            </a>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-orders">

                    <div class="empty-orders-icon">
                        ✦
                    </div>

                    <h4>
                        No orders yet
                    </h4>

                    <p>
                        Your recent purchases will appear here.
                    </p>

                    <a href="products.php">
                        Start Shopping →
                    </a>

                </div>

            <?php endif; ?>

        </section>



        <!-- =========================
             TRUST STRIP
        ========================== -->

        <section class="trust-strip">


            <div class="trust-item">

                <div class="trust-icon">
                    ✓
                </div>

                <div>

                    <strong>
                        Secure Shopping
                    </strong>

                    <span>
                        Your information stays protected
                    </span>

                </div>

            </div>



            <div class="trust-divider"></div>



            <div class="trust-item">

                <div class="trust-icon">
                    ⚡
                </div>

                <div>

                    <strong>
                        Fast Experience
                    </strong>

                    <span>
                        Simple and smooth shopping
                    </span>

                </div>

            </div>



            <div class="trust-divider"></div>



            <div class="trust-item">

                <div class="trust-icon">
                    ♢
                </div>

                <div>

                    <strong>
                        Quality First
                    </strong>

                    <span>
                        Products selected for you
                    </span>

                </div>

            </div>

        </section>



        <!-- FOOTER -->

        <footer class="dashboard-footer">

            <div>

                <strong>
                    QARTIV
                </strong>

                <span>
                    Smart E-Commerce Platform
                </span>

            </div>

            <p>
                © <?php echo date("Y"); ?> Qartiv.
                All rights reserved.
            </p>

        </footer>


    </main>

</div>

</body>
</html>