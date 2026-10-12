<?php
session_start();

require_once "../config/db.php";
require_once "../config/helpers.php";

/* =========================================================
   ADMIN SECURITY CHECK
========================================================= */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../customer/dashboard.php");
    exit;
}

$adminId = (int)$_SESSION["user_id"];
$adminName = $_SESSION["user_name"] ?? "Admin";

$message = "";
$messageType = "";

/* =========================================================
   HANDLE ACTIONS
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim($_POST["action"] ?? "");

    if ($action === "toggle_customer_status") {
        $targetUserId = (int)($_POST["user_id"] ?? 0);

        if ($targetUserId === $adminId) {
            $message = "You cannot block your own administrative account.";
            $messageType = "error";
        } elseif ($targetUserId > 0) {
            $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'blocked', 'active') WHERE id = ?");
            $stmt->bind_param("i", $targetUserId);
            if ($stmt->execute()) {
                $message = "Customer status updated successfully.";
                $messageType = "success";
            } else {
                $message = "Database error while updating customer status.";
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

/* =========================================================
   FILTERS & QUERY
========================================================= */
$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");

$sql = "
    SELECT u.id, u.name, u.email, u.role, u.status, u.created_at,
           COUNT(o.id) AS total_orders,
           COALESCE(SUM(CASE WHEN o.order_status != 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS total_spend,
           MAX(o.created_at) AS last_order_date
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE u.role = 'customer'
";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if (in_array($statusFilter, ["active", "blocked"], true)) {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " GROUP BY u.id ORDER BY u.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Metrics */
$totalCust = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")->fetch_assoc()["c"];
$activeCust = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer' AND status = 'active'")->fetch_assoc()["c"];
$blockedCust = $totalCust - $activeCust;
$lifetimeSpend = (float)$conn->query("SELECT COALESCE(SUM(total_amount), 0) AS s FROM orders WHERE order_status != 'cancelled'")->fetch_assoc()["s"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Accounts | QARTIV Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            background: #f7f8fc;
            color: #1a1e36;
            margin: 0;
        }

        .admin-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }

        .admin-sidebar {
            background: #101322;
            color: #ffffff;
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
        }

        .admin-brand small { color: #d4af37; font-size: 9px; letter-spacing: 2px; font-weight: 800; }
        .admin-brand strong { display: block; font-size: 22px; letter-spacing: 1px; color: #ffffff; margin-top: 2px; }

        .admin-nav { margin-top: 36px; flex: 1; }
        .nav-title { font-size: 10px; letter-spacing: 1.5px; color: #555e7d; font-weight: 800; margin-bottom: 12px; }

        .admin-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #a4adca;
            text-decoration: none;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            transition: 0.2s ease;
        }

        .admin-nav a:hover, .admin-nav a.active { background: #1a1e36; color: #ffffff; }
        .admin-nav a.active { border-left: 3px solid #d4af37; color: #d4af37; }

        .admin-main { padding: 32px 40px; }

        .top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .top-row h1 { font-size: 24px; font-weight: 850; margin: 0; color: #1a1e36; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid #e7e9f2;
            box-shadow: 0 4px 14px rgba(20,25,50,0.03);
        }

        .stat-label { font-size: 11px; color: #7b829c; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-val { font-size: 26px; font-weight: 850; color: #1a1e36; margin-top: 6px; }

        .panel-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e7e9f2;
            box-shadow: 0 4px 14px rgba(20,25,50,0.03);
            overflow: hidden;
        }

        .filter-row {
            padding: 18px 24px;
            border-bottom: 1px solid #f0f1f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f7f8fc;
            padding: 8px 16px;
            border-radius: 10px;
            border: 1px solid #e4e6f0;
            width: 320px;
        }

        .search-box input { border: none; outline: none; background: transparent; font-size: 13px; width: 100%; }

        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th {
            text-align: left;
            padding: 14px 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #777d94;
            background: #fafbfe;
            border-bottom: 1px solid #edf0f7;
        }

        .table-custom td {
            padding: 16px 20px;
            font-size: 13px;
            border-bottom: 1px solid #edf0f7;
            vertical-align: middle;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #eef0ff;
            color: #4f46e5;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .badge-active { background: #ecfdf5; color: #047857; }
        .badge-blocked { background: #fef2f2; color: #b91c1c; }

        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid #dcdfe8;
            background: #ffffff;
            color: #1a1e36;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-action:hover { background: #f4f5f8; }
        .btn-block { color: #dc2626; border-color: #fecaca; }
        .btn-block:hover { background: #fef2f2; }
        .btn-activate { color: #059669; border-color: #a7f3d0; }
        .btn-activate:hover { background: #ecfdf5; }
    </style>
</head>
<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <small>SMART COMMERCE</small>
            <strong>QARTIV</strong>
        </div>

        <nav class="admin-nav">
            <div class="nav-title">ADMIN PANEL</div>
            <a href="dashboard.php"><span class="nav-icon">⌂</span> Dashboard</a>
            <a href="products.php"><span class="nav-icon">◈</span> Products</a>
            <a href="categories.php"><span class="nav-icon">▦</span> Categories</a>
            <a href="orders.php"><span class="nav-icon">▣</span> Orders</a>
            <a href="customers.php" class="active"><span class="nav-icon">♙</span> Customers</a>
            <a href="coupons.php"><span class="nav-icon">%</span> Coupons</a>

            <div class="nav-title" style="margin-top:25px;">SYSTEM</div>
            <a href="../customer/dashboard.php"><span class="nav-icon">↗</span> Customer View</a>
            <a href="../index.php"><span class="nav-icon">🌐</span> Storefront</a>
        </nav>

        <div style="margin-top:auto;border-top:1px solid #20263f;padding-top:16px;">
            <small style="color:#777d94;font-size:11px;">ADMIN: <?php echo htmlspecialchars($adminName); ?></small>
            <div style="margin-top:8px;"><a href="../auth/logout.php" style="color:#f87171;text-decoration:none;font-size:12px;font-weight:700;">↪ Logout</a></div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="admin-main">

        <div class="top-row">
            <div>
                <h1>Customer Accounts</h1>
                <p style="margin:4px 0 0;color:#7b829c;font-size:13px;">Manage buyer profiles, spending history, and security statuses.</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div style="padding:14px 20px;border-radius:12px;margin-bottom:24px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;background:<?php echo $messageType === 'success' ? '#ecfdf5' : '#fef2f2'; ?>;color:<?php echo $messageType === 'success' ? '#065f46' : '#991b1b'; ?>;border:1px solid <?php echo $messageType === 'success' ? '#a7f3d0' : '#fecaca'; ?>;">
                <span><?php echo $messageType === 'success' ? '✓' : '⚠'; ?></span>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Customers</div>
                <div class="stat-val"><?php echo $totalCust; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Buyers</div>
                <div class="stat-val" style="color:#047857;"><?php echo $activeCust; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Suspended / Blocked</div>
                <div class="stat-val" style="color:#b91c1c;"><?php echo $blockedCust; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Customer Revenue</div>
                <div class="stat-val">₹<?php echo number_format($lifetimeSpend, 2); ?></div>
            </div>
        </div>

        <!-- TABLE PANEL -->
        <div class="panel-card">
            <div class="filter-row">
                <form method="GET" class="search-box">
                    <span>⌕</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search customer by name or email...">
                </form>

                <div style="display:flex;gap:8px;">
                    <a href="customers.php" class="btn-action">All</a>
                    <a href="customers.php?status=active" class="btn-action">Active</a>
                    <a href="customers.php?status=blocked" class="btn-action">Blocked</a>
                </div>
            </div>

            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width:50px;">Avatar</th>
                        <th>Customer</th>
                        <th>Registered Date</th>
                        <th>Orders Placed</th>
                        <th>Total Spend</th>
                        <th>Account Status</th>
                        <th style="text-align:right;">Security Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px;color:#969bab;">No customer records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($c['name'], 0, 1)); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($c['name']); ?></strong><br>
                                    <small style="color:#8c90a4;"><?php echo htmlspecialchars($c['email']); ?></small>
                                </td>
                                <td style="color:#555d70;">
                                    <?php echo date('M j, Y', strtotime($c['created_at'])); ?>
                                </td>
                                <td>
                                    <strong><?php echo (int)$c['total_orders']; ?></strong> order(s)
                                </td>
                                <td>
                                    <strong style="color:#1a1e36;">₹<?php echo number_format($c['total_spend'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge-status badge-<?php echo $c['status']; ?>">
                                        <?php echo htmlspecialchars($c['status']); ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <form method="POST" style="margin:0;display:inline;" onsubmit="return confirm('Change status for <?php echo addslashes($c['name']); ?>?');">
                                        <input type="hidden" name="action" value="toggle_customer_status">
                                        <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                                        <?php if ($c['status'] === 'active'): ?>
                                            <button type="submit" class="btn-action btn-block">Block Access</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-action btn-activate">Unblock User</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

</div>

</body>
</html>
