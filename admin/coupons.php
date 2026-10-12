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

$adminName = $_SESSION["user_name"] ?? "Admin";

$message = "";
$messageType = "";

/* =========================================================
   HANDLE ACTIONS
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim($_POST["action"] ?? "");

    /* 1. ADD COUPON */
    if ($action === "add_coupon") {
        $code = strtoupper(trim($_POST["code"] ?? ""));
        $type = in_array($_POST["discount_type"] ?? "", ["percentage", "fixed"], true) ? $_POST["discount_type"] : "percentage";
        $value = (float)($_POST["discount_value"] ?? 0);
        $minAmount = (float)($_POST["minimum_amount"] ?? 0);
        $expiry = !empty($_POST["expiry_date"]) ? $_POST["expiry_date"] : null;
        $status = in_array($_POST["status"] ?? "", ["active", "inactive"], true) ? $_POST["status"] : "active";

        if ($code === "") {
            $message = "Coupon code is required.";
            $messageType = "error";
        } elseif ($value <= 0) {
            $message = "Discount value must be greater than zero.";
            $messageType = "error";
        } elseif ($type === "percentage" && $value > 100) {
            $message = "Percentage discount cannot exceed 100%.";
            $messageType = "error";
        } else {
            // Check uniqueness
            $chk = $conn->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
            $chk->bind_param("s", $code);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $message = "Coupon code '{$code}' already exists.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("INSERT INTO coupons (code, discount_type, discount_value, minimum_amount, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssddss", $code, $type, $value, $minAmount, $expiry, $status);
                if ($stmt->execute()) {
                    $message = "Coupon '{$code}' created successfully.";
                    $messageType = "success";
                } else {
                    $message = "Failed to create coupon.";
                    $messageType = "error";
                }
                $stmt->close();
            }
            $chk->close();
        }
    }

    /* 2. EDIT COUPON */
    elseif ($action === "edit_coupon") {
        $couponId = (int)($_POST["coupon_id"] ?? 0);
        $type = in_array($_POST["discount_type"] ?? "", ["percentage", "fixed"], true) ? $_POST["discount_type"] : "percentage";
        $value = (float)($_POST["discount_value"] ?? 0);
        $minAmount = (float)($_POST["minimum_amount"] ?? 0);
        $expiry = !empty($_POST["expiry_date"]) ? $_POST["expiry_date"] : null;
        $status = in_array($_POST["status"] ?? "", ["active", "inactive"], true) ? $_POST["status"] : "active";

        if ($couponId <= 0 || $value <= 0) {
            $message = "Invalid coupon details.";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare("UPDATE coupons SET discount_type = ?, discount_value = ?, minimum_amount = ?, expiry_date = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sddssi", $type, $value, $minAmount, $expiry, $status, $couponId);
            if ($stmt->execute()) {
                $message = "Coupon updated successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to update coupon.";
                $messageType = "error";
            }
            $stmt->close();
        }
    }

    /* 3. DELETE COUPON */
    elseif ($action === "delete_coupon") {
        $couponId = (int)($_POST["coupon_id"] ?? 0);
        if ($couponId > 0) {
            $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
            $stmt->bind_param("i", $couponId);
            if ($stmt->execute()) {
                $message = "Coupon deleted successfully.";
                $messageType = "success";
            }
            $stmt->close();
        }
    }

    /* 4. TOGGLE STATUS */
    elseif ($action === "toggle_status") {
        $couponId = (int)($_POST["coupon_id"] ?? 0);
        if ($couponId > 0) {
            $stmt = $conn->prepare("UPDATE coupons SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
            $stmt->bind_param("i", $couponId);
            if ($stmt->execute()) {
                $message = "Coupon status updated.";
                $messageType = "success";
            }
            $stmt->close();
        }
    }
}

/* =========================================================
   FETCH COUPONS
========================================================= */
$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");

$sql = "SELECT * FROM coupons WHERE 1=1";
$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND code LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

if (in_array($statusFilter, ["active", "inactive"], true)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$coupons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Stats */
$totalCoupons = (int)$conn->query("SELECT COUNT(*) AS c FROM coupons")->fetch_assoc()["c"];
$activeCoupons = (int)$conn->query("SELECT COUNT(*) AS c FROM coupons WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE())")->fetch_assoc()["c"];
$expiredCoupons = (int)$conn->query("SELECT COUNT(*) AS c FROM coupons WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()")->fetch_assoc()["c"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons & Discounts | QARTIV Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background: #f7f8fc; color: #1a1e36; margin: 0; }
        .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .admin-sidebar { background: #101322; color: #ffffff; padding: 28px 20px; display: flex; flex-direction: column; }
        .admin-brand small { color: #d4af37; font-size: 9px; letter-spacing: 2px; font-weight: 800; }
        .admin-brand strong { display: block; font-size: 22px; letter-spacing: 1px; color: #ffffff; margin-top: 2px; }
        .admin-nav { margin-top: 36px; flex: 1; }
        .nav-title { font-size: 10px; letter-spacing: 1.5px; color: #555e7d; font-weight: 800; margin-bottom: 12px; }
        .admin-nav a {
            display: flex; align-items: center; gap: 12px; color: #a4adca; text-decoration: none;
            padding: 11px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 6px; transition: 0.2s ease;
        }
        .admin-nav a:hover, .admin-nav a.active { background: #1a1e36; color: #ffffff; }
        .admin-nav a.active { border-left: 3px solid #d4af37; color: #d4af37; }
        .admin-main { padding: 32px 40px; }
        .top-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .top-row h1 { font-size: 24px; font-weight: 850; margin: 0; color: #1a1e36; }
        .btn-add-primary {
            background: #1a1e36; color: #ffffff; border: none; padding: 12px 20px; border-radius: 10px;
            font-weight: 800; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s;
        }
        .btn-add-primary:hover { background: #d4af37; color: #1a1e36; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            background: #ffffff; padding: 20px; border-radius: 14px; border: 1px solid #e7e9f2;
            box-shadow: 0 4px 14px rgba(20,25,50,0.03);
        }
        .stat-label { font-size: 11px; color: #7b829c; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-val { font-size: 26px; font-weight: 850; color: #1a1e36; margin-top: 6px; }
        .panel-card { background: #ffffff; border-radius: 16px; border: 1px solid #e7e9f2; box-shadow: 0 4px 14px rgba(20,25,50,0.03); overflow: hidden; }
        .filter-row { padding: 18px 24px; border-bottom: 1px solid #f0f1f7; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .search-box {
            display: flex; align-items: center; gap: 10px; background: #f7f8fc; padding: 8px 16px;
            border-radius: 10px; border: 1px solid #e4e6f0; width: 300px;
        }
        .search-box input { border: none; outline: none; background: transparent; font-size: 13px; width: 100%; }
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th {
            text-align: left; padding: 14px 20px; font-size: 11px; text-transform: uppercase;
            letter-spacing: 1px; color: #777d94; background: #fafbfe; border-bottom: 1px solid #edf0f7;
        }
        .table-custom td { padding: 16px 20px; font-size: 13px; border-bottom: 1px solid #edf0f7; vertical-align: middle; }
        .coupon-badge {
            display: inline-block; padding: 5px 12px; border-radius: 8px; font-size: 12px; font-weight: 800;
            background: #fdfbf7; border: 1px dashed #d4af37; color: #855b14; letter-spacing: 1px;
        }
        .badge-status { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: capitalize; }
        .badge-active { background: #ecfdf5; color: #047857; }
        .badge-inactive { background: #fef2f2; color: #b91c1c; }
        .btn-action {
            padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer;
            border: 1px solid #dcdfe8; background: #ffffff; color: #1a1e36; transition: 0.2s;
        }
        .btn-action:hover { background: #f4f5f8; }
        .btn-delete { color: #dc2626; border-color: #fecaca; }
        .btn-delete:hover { background: #fef2f2; }

        /* Modal */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(16,19,34,0.6); z-index: 999; align-items: center; justify-content: center;
        }
        .modal-card {
            background: #ffffff; border-radius: 18px; width: 500px; max-width: 90%; padding: 32px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }
        .modal-card h2 { margin: 0 0 20px; font-size: 20px; color: #1a1e36; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 12px; font-weight: 800; margin-bottom: 6px; color: #4b526d; }
        .form-group input, .form-group select {
            width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid #dcdfe8;
            border-radius: 10px; font-size: 13px; outline: none; font-family: inherit;
        }
        .modal-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
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
            <a href="customers.php"><span class="nav-icon">♙</span> Customers</a>
            <a href="coupons.php" class="active"><span class="nav-icon">%</span> Coupons</a>

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
                <h1>Promotional Coupons</h1>
                <p style="margin:4px 0 0;color:#7b829c;font-size:13px;">Manage promo codes, percentages, and minimum order requirements.</p>
            </div>
            <button class="btn-add-primary" onclick="openAddModal()">+ Create New Coupon</button>
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
                <div class="stat-label">Total Coupons</div>
                <div class="stat-val"><?php echo $totalCoupons; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Promotions</div>
                <div class="stat-val" style="color:#047857;"><?php echo $activeCoupons; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Expired Codes</div>
                <div class="stat-val" style="color:#b91c1c;"><?php echo $expiredCoupons; ?></div>
            </div>
        </div>

        <!-- TABLE PANEL -->
        <div class="panel-card">
            <div class="filter-row">
                <form method="GET" class="search-box">
                    <span>⌕</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search coupon code...">
                </form>

                <div style="display:flex;gap:8px;">
                    <a href="coupons.php" class="btn-action">All</a>
                    <a href="coupons.php?status=active" class="btn-action">Active</a>
                    <a href="coupons.php?status=inactive" class="btn-action">Inactive</a>
                </div>
            </div>

            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Coupon Code</th>
                        <th>Discount Value</th>
                        <th>Min Order</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:#969bab;">No coupons created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $cp): ?>
                            <?php 
                            $isExpired = !empty($cp['expiry_date']) && strtotime($cp['expiry_date']) < strtotime(date('Y-m-d'));
                            ?>
                            <tr>
                                <td>
                                    <span class="coupon-badge"><?php echo htmlspecialchars($cp['code']); ?></span>
                                </td>
                                <td>
                                    <strong style="color:#1a1e36;">
                                        <?php if ($cp['discount_type'] === 'percentage'): ?>
                                            <?php echo number_format($cp['discount_value'], 0); ?>% OFF
                                        <?php else: ?>
                                            ₹<?php echo number_format($cp['discount_value'], 2); ?> FIXED OFF
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td>
                                    ₹<?php echo number_format($cp['minimum_amount'], 2); ?>
                                </td>
                                <td>
                                    <?php if (!empty($cp['expiry_date'])): ?>
                                        <span style="<?php echo $isExpired ? 'color:#dc2626;font-weight:700;' : 'color:#555d70;'; ?>">
                                            <?php echo date('M j, Y', strtotime($cp['expiry_date'])); ?>
                                            <?php if ($isExpired): ?><small>(Expired)</small><?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#8c90a4;">No Expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="margin:0;display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="coupon_id" value="<?php echo $cp['id']; ?>">
                                        <button type="submit" class="badge-status badge-<?php echo $cp['status']; ?>" style="border:none;cursor:pointer;" title="Click to toggle status">
                                            <?php echo htmlspecialchars($cp['status']); ?>
                                        </button>
                                    </form>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex;gap:8px;">
                                        <button 
                                            class="btn-action" 
                                            onclick='openEditModal(<?php echo json_encode($cp, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                                            Edit
                                        </button>
                                        <form method="POST" style="margin:0;display:inline;" onsubmit="return confirm('Delete coupon <?php echo addslashes($cp['code']); ?>?');">
                                            <input type="hidden" name="action" value="delete_coupon">
                                            <input type="hidden" name="coupon_id" value="<?php echo $cp['id']; ?>">
                                            <button type="submit" class="btn-action btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal-card">
        <h2>Create Promo Coupon</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_coupon">

            <div class="form-group">
                <label>Coupon Code *</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" name="code" id="add_code_input" required placeholder="e.g. VIP20" style="text-transform:uppercase;">
                    <button type="button" class="btn-action" onclick="generateCode()" style="white-space:nowrap;">Random</button>
                </div>
            </div>

            <div class="form-group">
                <label>Discount Type</label>
                <select name="discount_type" id="add_disc_type">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount (₹)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Discount Value *</label>
                <input type="number" step="0.01" name="discount_value" required placeholder="e.g. 15 for 15% or 200 for ₹200">
            </div>

            <div class="form-group">
                <label>Minimum Order Amount (₹)</label>
                <input type="number" step="0.01" name="minimum_amount" value="0.00" placeholder="0.00">
            </div>

            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-action" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn-add-primary">Create Coupon</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-card">
        <h2>Edit Coupon</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_coupon">
            <input type="hidden" name="coupon_id" id="edit_coupon_id">

            <div class="form-group">
                <label>Coupon Code</label>
                <input type="text" id="edit_code_view" readonly style="background:#f7f8fc;">
            </div>

            <div class="form-group">
                <label>Discount Type</label>
                <select name="discount_type" id="edit_discount_type">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount (₹)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Discount Value *</label>
                <input type="number" step="0.01" name="discount_value" id="edit_discount_value" required>
            </div>

            <div class="form-group">
                <label>Minimum Order Amount (₹)</label>
                <input type="number" step="0.01" name="minimum_amount" id="edit_minimum_amount">
            </div>

            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" id="edit_expiry_date">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-action" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn-add-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function generateCode() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = 'QTV';
    for (let i = 0; i < 5; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('add_code_input').value = code;
}
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}
function openEditModal(data) {
    document.getElementById('edit_coupon_id').value = data.id;
    document.getElementById('edit_code_view').value = data.code;
    document.getElementById('edit_discount_type').value = data.discount_type;
    document.getElementById('edit_discount_value').value = data.discount_value;
    document.getElementById('edit_minimum_amount').value = data.minimum_amount;
    document.getElementById('edit_expiry_date').value = data.expiry_date || '';
    document.getElementById('edit_status').value = data.status || 'active';
    document.getElementById('editModal').style.display = 'flex';
}
function closeModals() {
    document.getElementById('addModal').style.display = 'none';
    document.getElementById('editModal').style.display = 'none';
}
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        closeModals();
    }
}
</script>

</body>
</html>
