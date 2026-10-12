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
   HANDLE ACTIONS (ADD, EDIT, TOGGLE, DELETE)
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim($_POST["action"] ?? "");

    /* 1. ADD CATEGORY */
    if ($action === "add_category") {
        $name = trim($_POST["name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $status = in_array($_POST["status"] ?? "", ["active", "inactive"], true) ? $_POST["status"] : "active";

        if ($name === "") {
            $message = "Category name is required.";
            $messageType = "error";
        } else {
            // Check duplicate name
            $check = $conn->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
            $check->bind_param("s", $name);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "A category with this name already exists.";
                $messageType = "error";
            } else {
                // Upload image if provided
                $imageFilename = null;
                $uploadErr = "";
                if (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
                    $uploaded = validateAndUploadProductImage($_FILES["image"], $uploadErr);
                    if ($uploaded === false) {
                        $message = $uploadErr ?: "Image upload failed. Allowed formats: JPG, JPEG, PNG, WEBP.";
                        $messageType = "error";
                    } else {
                        $imageFilename = $uploaded;
                    }
                }

                if ($messageType !== "error") {
                    $stmt = $conn->prepare("INSERT INTO categories (name, description, image, status) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $description, $imageFilename, $status);
                    if ($stmt->execute()) {
                        $message = "Category '" . htmlspecialchars($name) . "' created successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Database error while adding category.";
                        $messageType = "error";
                    }
                    $stmt->close();
                }
            }
            $check->close();
        }
    }

    /* 2. EDIT CATEGORY */
    elseif ($action === "edit_category") {
        $catId = (int)($_POST["category_id"] ?? 0);
        $name = trim($_POST["name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $status = in_array($_POST["status"] ?? "", ["active", "inactive"], true) ? $_POST["status"] : "active";

        if ($catId <= 0 || $name === "") {
            $message = "Invalid category details.";
            $messageType = "error";
        } else {
            // Fetch existing
            $q = $conn->prepare("SELECT image FROM categories WHERE id = ? LIMIT 1");
            $q->bind_param("i", $catId);
            $q->execute();
            $existing = $q->get_result()->fetch_assoc();
            $q->close();

            if (!$existing) {
                $message = "Category not found.";
                $messageType = "error";
            } else {
                $imageFilename = $existing['image'];
                $uploadErr = "";

                if (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
                    $uploaded = validateAndUploadProductImage($_FILES["image"], $uploadErr);
                    if ($uploaded === false) {
                        $message = $uploadErr ?: "Image upload failed.";
                        $messageType = "error";
                    } else {
                        if (!empty($existing['image'])) {
                            deleteProductImage($existing['image']);
                        }
                        $imageFilename = $uploaded;
                    }
                }

                if ($messageType !== "error") {
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, image = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $name, $description, $imageFilename, $status, $catId);
                    if ($stmt->execute()) {
                        $message = "Category updated successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Failed to update category.";
                        $messageType = "error";
                    }
                    $stmt->close();
                }
            }
        }
    }

    /* 3. DELETE CATEGORY */
    elseif ($action === "delete_category") {
        $catId = (int)($_POST["category_id"] ?? 0);

        if ($catId > 0) {
            // Verify if any products are attached
            $chk = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE category_id = ?");
            $chk->bind_param("i", $catId);
            $chk->execute();
            $totalProducts = (int)$chk->get_result()->fetch_assoc()["total"];
            $chk->close();

            if ($totalProducts > 0) {
                $message = "Cannot delete category: {$totalProducts} product(s) are assigned to it. Reassign or delete those products first.";
                $messageType = "error";
            } else {
                $q = $conn->prepare("SELECT image FROM categories WHERE id = ? LIMIT 1");
                $q->bind_param("i", $catId);
                $q->execute();
                $existing = $q->get_result()->fetch_assoc();
                $q->close();

                if ($existing && !empty($existing['image'])) {
                    deleteProductImage($existing['image']);
                }

                $del = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $del->bind_param("i", $catId);
                if ($del->execute()) {
                    $message = "Category deleted successfully.";
                    $messageType = "success";
                } else {
                    $message = "Failed to delete category.";
                    $messageType = "error";
                }
                $del->close();
            }
        }
    }

    /* 4. TOGGLE STATUS */
    elseif ($action === "toggle_status") {
        $catId = (int)($_POST["category_id"] ?? 0);
        if ($catId > 0) {
            $stmt = $conn->prepare("UPDATE categories SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
            $stmt->bind_param("i", $catId);
            if ($stmt->execute()) {
                $message = "Category status toggled.";
                $messageType = "success";
            }
            $stmt->close();
        }
    }
}

/* =========================================================
   SEARCH & FETCH CATEGORIES
========================================================= */
$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");

$sql = "
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if (in_array($statusFilter, ["active", "inactive"], true)) {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " GROUP BY c.id ORDER BY c.id ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Stats */
$totalCats = (int)$conn->query("SELECT COUNT(*) AS c FROM categories")->fetch_assoc()["c"];
$activeCats = (int)$conn->query("SELECT COUNT(*) AS c FROM categories WHERE status = 'active'")->fetch_assoc()["c"];
$inactiveCats = $totalCats - $activeCats;
$totalProds = (int)$conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()["c"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management | QARTIV Admin</title>
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

        .admin-brand small {
            color: #d4af37;
            font-size: 9px;
            letter-spacing: 2px;
            font-weight: 800;
        }

        .admin-brand strong {
            display: block;
            font-size: 22px;
            letter-spacing: 1px;
            color: #ffffff;
            margin-top: 2px;
        }

        .admin-nav {
            margin-top: 36px;
            flex: 1;
        }

        .nav-title {
            font-size: 10px;
            letter-spacing: 1.5px;
            color: #555e7d;
            font-weight: 800;
            margin-bottom: 12px;
        }

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

        .admin-nav a:hover, .admin-nav a.active {
            background: #1a1e36;
            color: #ffffff;
        }

        .admin-nav a.active {
            border-left: 3px solid #d4af37;
            color: #d4af37;
        }

        .admin-main {
            padding: 32px 40px;
        }

        .top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .top-row h1 {
            font-size: 24px;
            font-weight: 850;
            margin: 0;
            color: #1a1e36;
        }

        .btn-add-primary {
            background: #1a1e36;
            color: #ffffff;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-add-primary:hover {
            background: #d4af37;
            color: #1a1e36;
        }

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

        .stat-label {
            font-size: 11px;
            color: #7b829c;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .stat-val {
            font-size: 26px;
            font-weight: 850;
            color: #1a1e36;
            margin-top: 6px;
        }

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
            width: 300px;
        }

        .search-box input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 13px;
            width: 100%;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
        }

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

        .cat-thumb {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #ede8de;
            background: #faf8f5;
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
        .badge-inactive { background: #fef2f2; color: #b91c1c; }

        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid #dcdfe8;
            background: #ffffff;
            color: #1a1e36;
            transition: 0.2s;
        }

        .btn-action:hover { background: #f4f5f8; }
        .btn-delete { color: #dc2626; border-color: #fecaca; }
        .btn-delete:hover { background: #fef2f2; }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(16,19,34,0.6);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }

        .modal-card {
            background: #ffffff;
            border-radius: 18px;
            width: 500px;
            max-width: 90%;
            padding: 32px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }

        .modal-card h2 {
            margin: 0 0 20px;
            font-size: 20px;
            color: #1a1e36;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 6px;
            color: #4b526d;
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 14px;
            border: 1px solid #dcdfe8;
            border-radius: 10px;
            font-size: 13px;
            outline: none;
            font-family: inherit;
        }

        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #1a1e36;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }
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
            <a href="categories.php" class="active"><span class="nav-icon">▦</span> Categories</a>
            <a href="orders.php"><span class="nav-icon">▣</span> Orders</a>
            <a href="customers.php"><span class="nav-icon">♙</span> Customers</a>
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
                <h1>Categories Management</h1>
                <p style="margin:4px 0 0;color:#7b829c;font-size:13px;">Create, organize, and manage storefront product categories.</p>
            </div>
            <button class="btn-add-primary" onclick="openAddModal()">+ Add New Category</button>
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
                <div class="stat-label">Total Categories</div>
                <div class="stat-val"><?php echo $totalCats; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Categories</div>
                <div class="stat-val" style="color:#047857;"><?php echo $activeCats; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Inactive Categories</div>
                <div class="stat-val" style="color:#b91c1c;"><?php echo $inactiveCats; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Catalog Products</div>
                <div class="stat-val"><?php echo $totalProds; ?></div>
            </div>
        </div>

        <!-- TABLE PANEL -->
        <div class="panel-card">
            <div class="filter-row">
                <form method="GET" class="search-box">
                    <span>⌕</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search category...">
                </form>

                <div style="display:flex;gap:8px;">
                    <a href="categories.php" class="btn-action <?php echo $statusFilter === '' ? 'style="font-weight:850;"' : ''; ?>">All</a>
                    <a href="categories.php?status=active" class="btn-action">Active</a>
                    <a href="categories.php?status=inactive" class="btn-action">Inactive</a>
                </div>
            </div>

            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width:70px;">Image</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:#969bab;">No categories found matching your query.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <img 
                                        src="<?php echo htmlspecialchars(getProductImageUrl($cat['image'], '../')); ?>" 
                                        alt="<?php echo htmlspecialchars($cat['name']); ?>" 
                                        class="cat-thumb">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($cat['name']); ?></strong><br>
                                    <small style="color:#8c90a4;">ID #<?php echo $cat['id']; ?></small>
                                </td>
                                <td style="max-width:300px;color:#555d70;">
                                    <?php echo htmlspecialchars($cat['description'] ?: '—'); ?>
                                </td>
                                <td>
                                    <span style="font-weight:800;color:#1a1e36;"><?php echo (int)$cat['product_count']; ?></span> items
                                </td>
                                <td>
                                    <form method="POST" style="margin:0;display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="badge-status badge-<?php echo $cat['status']; ?>" style="border:none;cursor:pointer;" title="Click to toggle status">
                                            <?php echo htmlspecialchars($cat['status']); ?>
                                        </button>
                                    </form>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex;gap:8px;">
                                        <button 
                                            class="btn-action" 
                                            onclick='openEditModal(<?php echo json_encode($cat, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                                            Edit
                                        </button>
                                        <form method="POST" style="margin:0;display:inline;" onsubmit="return confirm('Are you sure you want to delete category \'<?php echo addslashes($cat['name']); ?>\'?');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
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
        <h2>Add New Category</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_category">

            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="name" required placeholder="e.g. Premium Audio">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Category brief..."></textarea>
            </div>

            <div class="form-group">
                <label>Category Banner Image (Optional)</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
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
                <button type="submit" class="btn-add-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-card">
        <h2>Edit Category</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="category_id" id="edit_cat_id">

            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="name" id="edit_cat_name" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_cat_desc" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>Change Image (Optional)</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_cat_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-action" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn-add-primary">Update Category</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}
function openEditModal(data) {
    document.getElementById('edit_cat_id').value = data.id;
    document.getElementById('edit_cat_name').value = data.name;
    document.getElementById('edit_cat_desc').value = data.description || '';
    document.getElementById('edit_cat_status').value = data.status || 'active';
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
