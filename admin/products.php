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
   HANDLE POST ACTIONS (ADD, EDIT, DELETE)
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = trim($_POST["action"] ?? "");

    /* -----------------------------------------------------
       1. ADD PRODUCT
    ----------------------------------------------------- */
    if ($action === "add_product") {
        $name = trim($_POST["name"] ?? "");
        $categoryId = (int)($_POST["category_id"] ?? 0);
        $description = trim($_POST["description"] ?? "");
        $price = (float)($_POST["price"] ?? 0);
        $discount = (float)($_POST["discount"] ?? 0);
        $stock = (int)($_POST["stock"] ?? 0);
        $status = in_array($_POST["status"] ?? "", ["active", "inactive"], true) ? $_POST["status"] : "active";

        if ($name === "") {
            $message = "Product name is required.";
            $messageType = "error";
        } elseif ($categoryId <= 0) {
            $message = "Please select a valid category.";
            $messageType = "error";
        } elseif ($price < 0) {
            $message = "Price cannot be negative.";
            $messageType = "error";
        } elseif ($discount < 0 || $discount > 100) {
            $message = "Discount must be between 0% and 100%.";
            $messageType = "error";
        } elseif ($stock < 0) {
            $message = "Stock cannot be negative.";
            $messageType = "error";
        } else {
            // Handle image upload if provided
            $imageFilename = null;
            $uploadError = "";

            if (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
                $uploaded = validateAndUploadProductImage($_FILES["image"], $uploadError);
                if ($uploaded === false) {
                    $message = $uploadError ?: "Image upload failed. Allowed formats: JPG, JPEG, PNG, WEBP (Max 5MB).";
                    $messageType = "error";
                } else {
                    $imageFilename = $uploaded;
                }
            }

            if ($messageType !== "error") {
                $stmt = $conn->prepare(
                    "INSERT INTO products (category_id, name, description, price, discount, stock, image, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if ($stmt) {
                    $stmt->bind_param("issddiss", $categoryId, $name, $description, $price, $discount, $stock, $imageFilename, $status);
                    if ($stmt->execute()) {
                        $message = "Product '" . htmlspecialchars($name) . "' created successfully.";
                        $messageType = "success";
                    } else {
                        // If DB insert failed and image was uploaded, clean up orphan image
                        if ($imageFilename) {
                            deleteProductImage($imageFilename);
                        }
                        $message = "Database error: Could not create product.";
                        $messageType = "error";
                    }
                    $stmt->close();
                } else {
                    $message = "Failed to prepare database statement.";
                    $messageType = "error";
                }
            }
        }
    }

    /* -----------------------------------------------------
       2. EDIT / UPDATE PRODUCT
    ----------------------------------------------------- */
    elseif ($action === "edit_product") {
        $productId = (int)($_POST["product_id"] ?? 0);
        $name = trim($_POST["name"] ?? "");
        $categoryId = (int)($_POST["category_id"] ?? 0);
        $description = trim($_POST["description"] ?? "");
        $price = (float)($_POST["price"] ?? 0);
        $discount = (float)($_POST["discount"] ?? 0);
        $stock = (int)($_POST["stock"] ?? 0);
        $status = in_array($_POST["status"] ?? "", ["active", "inactive"], true) ? $_POST["status"] : "active";
        $deleteImageFlag = isset($_POST["delete_image"]) && $_POST["delete_image"] === "1";

        if ($productId <= 0) {
            $message = "Invalid product ID.";
            $messageType = "error";
        } elseif ($name === "") {
            $message = "Product name is required.";
            $messageType = "error";
        } elseif ($categoryId <= 0) {
            $message = "Please select a valid category.";
            $messageType = "error";
        } elseif ($price < 0) {
            $message = "Price cannot be negative.";
            $messageType = "error";
        } elseif ($discount < 0 || $discount > 100) {
            $message = "Discount must be between 0% and 100%.";
            $messageType = "error";
        } elseif ($stock < 0) {
            $message = "Stock cannot be negative.";
            $messageType = "error";
        } else {
            // Fetch current product to check existing image
            $currentImage = null;
            $fetchStmt = $conn->prepare("SELECT image FROM products WHERE id = ? LIMIT 1");
            if ($fetchStmt) {
                $fetchStmt->bind_param("i", $productId);
                $fetchStmt->execute();
                $fetchRes = $fetchStmt->get_result();
                if ($prodRow = $fetchRes->fetch_assoc()) {
                    $currentImage = $prodRow["image"];
                }
                $fetchStmt->close();
            }

            $newImageFilename = $currentImage;
            $newUploadOccurred = false;

            // Check if user requested to delete existing image
            if ($deleteImageFlag && empty($_FILES["image"]["name"])) {
                if (!empty($currentImage)) {
                    deleteProductImage($currentImage);
                }
                $newImageFilename = null;
            }

            // Check if a new image was uploaded to replace existing
            if (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
                $uploadError = "";
                $uploaded = validateAndUploadProductImage($_FILES["image"], $uploadError);
                if ($uploaded === false) {
                    $message = $uploadError ?: "Image replacement failed. Check format and size.";
                    $messageType = "error";
                } else {
                    $newImageFilename = $uploaded;
                    $newUploadOccurred = true;
                }
            }

            if ($messageType !== "error") {
                $updateStmt = $conn->prepare(
                    "UPDATE products
                     SET category_id = ?, name = ?, description = ?, price = ?, discount = ?, stock = ?, image = ?, status = ?
                     WHERE id = ?"
                );

                if ($updateStmt) {
                    $updateStmt->bind_param("issddissi", $categoryId, $name, $description, $price, $discount, $stock, $newImageFilename, $status, $productId);
                    if ($updateStmt->execute()) {
                        // If new image successfully saved and an old one existed, delete old file
                        if ($newUploadOccurred && !empty($currentImage) && $currentImage !== $newImageFilename) {
                            deleteProductImage($currentImage);
                        }
                        $message = "Product '" . htmlspecialchars($name) . "' updated successfully.";
                        $messageType = "success";
                    } else {
                        // Roll back newly uploaded file if DB update failed
                        if ($newUploadOccurred && !empty($newImageFilename)) {
                            deleteProductImage($newImageFilename);
                        }
                        $message = "Database error: Unable to update product.";
                        $messageType = "error";
                    }
                    $updateStmt->close();
                } else {
                    $message = "Failed to prepare update query.";
                    $messageType = "error";
                }
            }
        }
    }

    /* -----------------------------------------------------
       3. DELETE PRODUCT
    ----------------------------------------------------- */
    elseif ($action === "delete_product") {
        $productId = (int)($_POST["product_id"] ?? 0);

        if ($productId > 0) {
            // First fetch the image to delete from disk
            $imageToDelete = null;
            $chkStmt = $conn->prepare("SELECT image FROM products WHERE id = ? LIMIT 1");
            if ($chkStmt) {
                $chkStmt->bind_param("i", $productId);
                $chkStmt->execute();
                $res = $chkStmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $imageToDelete = $row["image"];
                }
                $chkStmt->close();
            }

            // Delete product record from database
            $delStmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            if ($delStmt) {
                $delStmt->bind_param("i", $productId);
                if ($delStmt->execute()) {
                    if (!empty($imageToDelete)) {
                        deleteProductImage($imageToDelete);
                    }
                    $message = "Product #{$productId} deleted successfully.";
                    $messageType = "success";
                } else {
                    $message = "Could not delete product. It may be linked to existing order items.";
                    $messageType = "error";
                }
                $delStmt->close();
            }
        } else {
            $message = "Invalid product selected for deletion.";
            $messageType = "error";
        }
    }
}

/* =========================================================
   FETCH CATEGORIES (FOR DROPDOWNS & FILTER)
========================================================= */
$categories = [];
$catRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($catRes) {
    while ($c = $catRes->fetch_assoc()) {
        $categories[] = $c;
    }
}

/* =========================================================
   SEARCH & FILTER PARAMS
========================================================= */
$search = trim($_GET["search"] ?? "");
$filterCategory = (int)($_GET["category"] ?? 0);
$filterStatus = trim($_GET["status"] ?? "");
$filterStock = trim($_GET["stock"] ?? "");

/* =========================================================
   FETCH PRODUCTS WITH FILTERS
========================================================= */
$whereConditions = ["1=1"];
$params = [];
$types = "";

if ($search !== "") {
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if ($filterCategory > 0) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $filterCategory;
    $types .= "i";
}

if ($filterStatus === "active" || $filterStatus === "inactive") {
    $whereConditions[] = "p.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if ($filterStock === "out") {
    $whereConditions[] = "p.stock <= 0";
} elseif ($filterStock === "low") {
    $whereConditions[] = "p.stock > 0 AND p.stock <= 5";
} elseif ($filterStock === "in") {
    $whereConditions[] = "p.stock > 5";
}

$whereSql = implode(" AND ", $whereConditions);

$querySql = "
    SELECT 
        p.id,
        p.category_id,
        p.name,
        p.description,
        p.price,
        p.discount,
        p.stock,
        p.image,
        p.status,
        p.created_at,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$whereSql}
    ORDER BY p.id DESC
";

$products = [];
$stmt = $conn->prepare($querySql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
}

/* =========================================================
   CALCULATE INVENTORY STATS
========================================================= */
$statTotal = 0;
$statActive = 0;
$statLowStock = 0;
$statOutOfStock = 0;

$statsRes = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_cnt,
        SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS out_cnt,
        SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END) AS low_cnt
    FROM products
");
if ($statsRes && $row = $statsRes->fetch_assoc()) {
    $statTotal = (int)($row["total"] ?? 0);
    $statActive = (int)($row["active_cnt"] ?? 0);
    $statOutOfStock = (int)($row["out_cnt"] ?? 0);
    $statLowStock = (int)($row["low_cnt"] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management | QARTIV Admin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', Inter, -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f7fc;
            color: #171923;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Layout */
        .admin-layout {
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar matching dashboard.php */
        .admin-sidebar {
            width: 260px;
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
            z-index: 100;
        }

        .admin-brand {
            padding: 0 12px 28px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .admin-brand small {
            display: block;
            color: #8e94a7;
            font-size: 10px;
            letter-spacing: 2px;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .admin-brand strong {
            font-size: 24px;
            letter-spacing: 2px;
            font-weight: 900;
        }

        .admin-nav {
            margin-top: 26px;
        }

        .nav-title {
            color: #747b91;
            font-size: 10px;
            letter-spacing: 1.5px;
            padding: 0 12px;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .admin-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            margin-bottom: 5px;
            border-radius: 12px;
            color: #aeb3c3;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
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
            background: rgba(255,255,255,0.06);
            font-size: 14px;
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
            margin-bottom: 4px;
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

        /* Main Content */
        .admin-main {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            padding: 32px 40px 60px;
        }

        /* Topbar */
        .admin-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .admin-topbar small {
            color: #858b9d;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .admin-topbar h1 {
            font-size: 28px;
            font-weight: 900;
            color: #171927;
            margin-top: 4px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-add-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #6258ff, #7c4eea);
            color: #ffffff;
            padding: 12px 22px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(98, 88, 255, 0.28);
            transition: all 0.25s ease;
        }

        .btn-add-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(98, 88, 255, 0.38);
        }

        /* Alert Notification */
        .alert-box {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            font-weight: 700;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #eafaf1;
            color: #187948;
            border: 1px solid #b7ebd0;
        }

        .alert-error {
            background: #fff1f2;
            color: #b91c1c;
            border: 1px solid #fed7d7;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card-widget {
            background: #ffffff;
            border: 1px solid #e7e9f1;
            border-radius: 18px;
            padding: 20px 22px;
            box-shadow: 0 6px 20px rgba(25, 29, 65, 0.035);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .stat-card-widget:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(25, 29, 65, 0.07);
        }

        .stat-card-widget .stat-meta small {
            display: block;
            color: #888ea2;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }

        .stat-card-widget .stat-meta strong {
            font-size: 28px;
            font-weight: 900;
            color: #171927;
        }

        .stat-icon-bubble {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .bubble-purple { background: #f0efff; color: #6258ff; }
        .bubble-green { background: #eafaf1; color: #187948; }
        .bubble-orange { background: #fff8eb; color: #b7791f; }
        .bubble-red { background: #fff1f2; color: #e53e3e; }

        /* Filter & Search Toolbar */
        .filter-toolbar {
            background: #ffffff;
            border: 1px solid #e7e9f1;
            border-radius: 18px;
            padding: 18px 22px;
            margin-bottom: 24px;
            box-shadow: 0 5px 18px rgba(25, 29, 65, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .search-form-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 280px;
        }

        .search-input-box {
            flex: 1;
            position: relative;
        }

        .search-input-box input {
            width: 100%;
            height: 44px;
            padding: 0 14px 0 38px;
            border: 1px solid #e2e4ed;
            border-radius: 12px;
            font-size: 13px;
            font-family: inherit;
            background: #f9fafd;
            color: #1a1e36;
            outline: none;
            transition: all 0.2s ease;
        }

        .search-input-box input:focus {
            border-color: #6258ff;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(98, 88, 255, 0.12);
        }

        .search-input-box .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa1b5;
            font-size: 15px;
            pointer-events: none;
        }

        .filter-selects {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .custom-select {
            height: 44px;
            padding: 0 14px;
            border: 1px solid #e2e4ed;
            border-radius: 12px;
            font-size: 13px;
            font-family: inherit;
            background: #ffffff;
            color: #33384f;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
        }

        .custom-select:focus {
            border-color: #6258ff;
        }

        .btn-filter-action {
            height: 44px;
            padding: 0 18px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-filter-submit {
            background: #171927;
            color: #ffffff;
        }

        .btn-filter-submit:hover {
            background: #2c3044;
        }

        .btn-filter-reset {
            background: #f1f3f9;
            color: #555b70;
        }

        .btn-filter-reset:hover {
            background: #e4e7f2;
        }

        /* Products Table Panel */
        .table-panel {
            background: #ffffff;
            border: 1px solid #e7e9f1;
            border-radius: 20px;
            box-shadow: 0 6px 25px rgba(25, 29, 65, 0.04);
            overflow: hidden;
        }

        .table-panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f1f5;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-panel-header h3 {
            font-size: 17px;
            font-weight: 800;
            color: #171927;
        }

        .table-panel-header span {
            color: #8c92a6;
            font-size: 12px;
            font-weight: 600;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        .admin-product-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .admin-product-table th {
            padding: 14px 18px;
            background: #fafafc;
            color: #8a91a5;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid #eef0f6;
        }

        .admin-product-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #f1f3f8;
            font-size: 13px;
            vertical-align: middle;
            color: #2b3045;
        }

        .admin-product-table tr:hover td {
            background: #fbfbfe;
        }

        /* Thumbnail styling */
        .thumb-box {
            width: 58px;
            height: 58px;
            border-radius: 12px;
            overflow: hidden;
            background: #f0f2f7;
            border: 1px solid #e6e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
        }

        .thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.25s ease;
        }

        .admin-product-table tr:hover .thumb-box img {
            transform: scale(1.08);
        }

        .thumb-badge {
            position: absolute;
            bottom: 2px;
            right: 2px;
            background: rgba(17,19,28,0.75);
            color: white;
            font-size: 8px;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: 700;
        }

        .product-name-cell {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .product-meta-text strong {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #171927;
            margin-bottom: 3px;
        }

        .product-meta-text span {
            display: block;
            color: #8e95a9;
            font-size: 11px;
            max-width: 260px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Badges */
        .cat-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #f1f0ff;
            color: #594ee6;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
        }

        .price-stack strong {
            display: block;
            font-size: 14px;
            font-weight: 800;
            color: #171927;
        }

        .price-stack del {
            display: block;
            color: #9ba1b4;
            font-size: 11px;
        }

        .discount-pill {
            display: inline-block;
            padding: 4px 8px;
            background: #fff4e5;
            color: #b7791f;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
        }

        .discount-zero {
            color: #a4abbd;
            font-size: 12px;
        }

        .stock-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
        }

        .stock-in {
            background: #eafaf1;
            color: #187948;
        }

        .stock-low {
            background: #fff8eb;
            color: #b7791f;
        }

        .stock-out {
            background: #fff1f2;
            color: #c53030;
        }

        .stock-dot-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .status-active {
            background: #eafaf1;
            color: #187948;
        }

        .status-inactive {
            background: #f1f3f8;
            color: #7a8296;
        }

        /* Action Buttons */
        .actions-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e5ee;
            background: #ffffff;
            color: #4b5269;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-action-icon:hover {
            border-color: #6258ff;
            color: #6258ff;
            background: #f6f5ff;
        }

        .btn-action-delete:hover {
            border-color: #e53e3e;
            color: #e53e3e;
            background: #fff5f5;
        }

        /* Empty State */
        .empty-table-state {
            padding: 60px 20px;
            text-align: center;
            color: #8c93a8;
        }

        .empty-table-state span {
            font-size: 40px;
            display: block;
            margin-bottom: 12px;
        }

        .empty-table-state h4 {
            font-size: 17px;
            font-weight: 800;
            color: #171927;
            margin-bottom: 6px;
        }

        .empty-table-state p {
            font-size: 13px;
            margin-bottom: 16px;
        }

        /* =========================================================
           MODALS (ADD & EDIT)
        ========================================================= */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(14, 17, 30, 0.65);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: 24px;
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(10, 15, 45, 0.22);
            animation: modalPop 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid #edf0f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 800;
            color: #171927;
        }

        .modal-close {
            background: #f1f3f8;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            color: #636b83;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: #e2e6f0;
            color: #171927;
        }

        .modal-body {
            padding: 26px 28px;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #2e344d;
            margin-bottom: 7px;
            letter-spacing: 0.2px;
        }

        .form-label span.req {
            color: #e53e3e;
        }

        .form-control {
            width: 100%;
            height: 46px;
            padding: 0 15px;
            border: 1px solid #dfe3ee;
            border-radius: 12px;
            font-size: 13px;
            font-family: inherit;
            color: #171927;
            background: #fbfcfe;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #6258ff;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(98, 88, 255, 0.12);
        }

        textarea.form-control {
            height: 90px;
            padding: 12px 15px;
            resize: vertical;
        }

        /* Image Upload Box */
        .image-upload-wrapper {
            border: 2px dashed #d5d9e6;
            border-radius: 16px;
            padding: 20px;
            background: #fcfdfe;
            text-align: center;
            cursor: pointer;
            transition: all 0.22s ease;
            position: relative;
        }

        .image-upload-wrapper:hover,
        .image-upload-wrapper.dragover {
            border-color: #6258ff;
            background: #f7f6ff;
        }

        .image-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 5;
        }

        .upload-icon {
            font-size: 32px;
            color: #6258ff;
            margin-bottom: 8px;
            display: block;
        }

        .upload-text strong {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #1c2035;
            margin-bottom: 3px;
        }

        .upload-text small {
            color: #8c93a8;
            font-size: 11px;
            display: block;
        }

        .image-preview-strip {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 14px;
            padding: 12px;
            background: #f4f6fb;
            border-radius: 12px;
        }

        .preview-img-box {
            width: 64px;
            height: 64px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #dfe3ee;
            background: #ffffff;
            flex-shrink: 0;
        }

        .preview-img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-meta {
            flex: 1;
            text-align: left;
        }

        .preview-meta strong {
            display: block;
            font-size: 12px;
            color: #171927;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px;
        }

        .preview-meta small {
            color: #7b8298;
            font-size: 10px;
        }

        .current-image-preview {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
            padding: 12px;
            background: #f7f8fc;
            border-radius: 12px;
            border: 1px solid #e6e9f1;
        }

        .current-image-preview img {
            width: 54px;
            height: 54px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #dfe3ee;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid #edf0f6;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            background: #fafafc;
            border-radius: 0 0 24px 24px;
        }

        .btn-modal-cancel {
            padding: 11px 20px;
            border-radius: 11px;
            background: #eef0f6;
            color: #4b5269;
            font-size: 13px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-modal-cancel:hover {
            background: #e2e5ef;
        }

        .btn-modal-save {
            padding: 11px 24px;
            border-radius: 11px;
            background: linear-gradient(135deg, #6258ff, #7c4eea);
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(98, 88, 255, 0.25);
            transition: all 0.2s ease;
        }

        .btn-modal-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(98, 88, 255, 0.35);
        }

        /* Delete Confirmation Dialog */
        .confirm-dialog {
            max-width: 440px;
            text-align: center;
            padding: 30px;
        }

        .confirm-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #fff1f2;
            color: #e53e3e;
            font-size: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .confirm-dialog h3 {
            font-size: 18px;
            font-weight: 800;
            color: #171927;
            margin-bottom: 8px;
        }

        .confirm-dialog p {
            color: #7b8298;
            font-size: 13px;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .confirm-dialog-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .btn-confirm-delete {
            padding: 11px 24px;
            border-radius: 11px;
            background: #e53e3e;
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-confirm-delete:hover {
            background: #c53030;
        }

        /* Responsive Media Queries */
        @media (max-width: 1100px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 820px) {
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
                padding: 24px 18px;
            }
            .filter-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .search-form-group {
                width: 100%;
            }
            .stats-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .admin-topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }
        }
    </style>
</head>
<body>

<div class="admin-layout">

    <!-- =========================================================
         SIDEBAR
    ========================================================= -->
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <small>SMART COMMERCE</small>
            <strong>QARTIV</strong>
        </div>

        <nav class="admin-nav">
            <div class="nav-title">ADMIN PANEL</div>

            <a href="dashboard.php">
                <span class="nav-icon">⌂</span>
                Dashboard
            </a>

            <a href="products.php" class="active">
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

            <div class="nav-title" style="margin-top: 25px;">SYSTEM</div>

            <a href="../customer/products.php" target="_blank">
                <span class="nav-icon">↗</span>
                Customer Store
            </a>

            <a href="../customer/dashboard.php">
                <span class="nav-icon">👤</span>
                Customer View
            </a>
        </nav>

        <div class="admin-sidebar-bottom">
            <div class="admin-user-box">
                <small>LOGGED IN AS</small>
                <strong><?php echo htmlspecialchars($adminName); ?></strong>
            </div>

            <a href="../auth/logout.php" class="admin-nav logout-link">
                <span class="nav-icon">↪</span>
                Logout
            </a>
        </div>
    </aside>

    <!-- =========================================================
         MAIN CONTENT
    ========================================================= -->
    <main class="admin-main">

        <!-- Top Bar -->
        <header class="admin-topbar">
            <div>
                <small>QARTIV / INVENTORY</small>
                <h1>Product Management</h1>
            </div>

            <div class="topbar-actions">
                <button type="button" class="btn-add-primary" onclick="openAddModal()">
                    <span style="font-size: 16px;">+</span>
                    Add New Product
                </button>
            </div>
        </header>

        <!-- Alert Notification -->
        <?php if ($message !== ""): ?>
            <div class="alert-box alert-<?php echo htmlspecialchars($messageType); ?>">
                <span><?php echo $messageType === "success" ? "✓" : "⚠"; ?></span>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Inventory Stats Cards -->
        <section class="stats-row">
            <div class="stat-card-widget">
                <div class="stat-meta">
                    <small>Total Products</small>
                    <strong><?php echo $statTotal; ?></strong>
                </div>
                <div class="stat-icon-bubble bubble-purple">◈</div>
            </div>

            <div class="stat-card-widget">
                <div class="stat-meta">
                    <small>Active Listings</small>
                    <strong><?php echo $statActive; ?></strong>
                </div>
                <div class="stat-icon-bubble bubble-green">✓</div>
            </div>

            <div class="stat-card-widget">
                <div class="stat-meta">
                    <small>Low Stock (&le; 5)</small>
                    <strong><?php echo $statLowStock; ?></strong>
                </div>
                <div class="stat-icon-bubble bubble-orange">▲</div>
            </div>

            <div class="stat-card-widget">
                <div class="stat-meta">
                    <small>Out of Stock</small>
                    <strong><?php echo $statOutOfStock; ?></strong>
                </div>
                <div class="stat-icon-bubble bubble-red">✕</div>
            </div>
        </section>

        <!-- Filters & Search Toolbar -->
        <section class="filter-toolbar">
            <form method="GET" action="products.php" class="search-form-group">
                <div class="search-input-box">
                    <span class="search-icon">⌕</span>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($search); ?>" 
                        placeholder="Search product name, description..."
                    >
                </div>

                <select name="category" class="custom-select">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int)$cat["id"]; ?>" <?php echo $filterCategory === (int)$cat["id"] ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($cat["name"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="custom-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $filterStatus === "active" ? "selected" : ""; ?>>Active</option>
                    <option value="inactive" <?php echo $filterStatus === "inactive" ? "selected" : ""; ?>>Inactive</option>
                </select>

                <select name="stock" class="custom-select">
                    <option value="">All Stock</option>
                    <option value="in" <?php echo $filterStock === "in" ? "selected" : ""; ?>>In Stock</option>
                    <option value="low" <?php echo $filterStock === "low" ? "selected" : ""; ?>>Low Stock</option>
                    <option value="out" <?php echo $filterStock === "out" ? "selected" : ""; ?>>Out of Stock</option>
                </select>

                <button type="submit" class="btn-filter-action btn-filter-submit">
                    Filter
                </button>

                <?php if ($search !== "" || $filterCategory > 0 || $filterStatus !== "" || $filterStock !== ""): ?>
                    <a href="products.php" class="btn-filter-action btn-filter-reset">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </section>

        <!-- Products Table Panel -->
        <section class="table-panel">
            <div class="table-panel-header">
                <h3>Catalog Products</h3>
                <span>Showing <?php echo count($products); ?> product<?php echo count($products) !== 1 ? "s" : ""; ?></span>
            </div>

            <div class="table-container">
                <table class="admin-product-table">
                    <thead>
                        <tr>
                            <th style="width: 70px;">Image</th>
                            <th>Product Info</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Discount</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $prod): ?>
                                <?php
                                    $pId = (int)$prod["id"];
                                    $origPrice = (float)$prod["price"];
                                    $disc = (float)$prod["discount"];
                                    $finalPrice = calculateSalePrice($origPrice, $disc);
                                    $stockQty = (int)$prod["stock"];
                                    $statusVal = $prod["status"];
                                    $imgUrl = getProductImageUrl($prod["image"], "../");
                                ?>
                                <tr>
                                    <!-- Image Thumbnail -->
                                    <td>
                                        <div class="thumb-box" title="Product #<?php echo $pId; ?>">
                                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($prod["name"]); ?>" loading="lazy">
                                            <?php if (empty($prod["image"])): ?>
                                                <span class="thumb-badge">Default</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Product Name & Description -->
                                    <td>
                                        <div class="product-name-cell">
                                            <div class="product-meta-text">
                                                <strong><?php echo htmlspecialchars($prod["name"]); ?></strong>
                                                <span><?php echo htmlspecialchars($prod["description"] ?: "No description provided."); ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Category -->
                                    <td>
                                        <span class="cat-badge">
                                            <?php echo htmlspecialchars($prod["category_name"] ?? "Uncategorized"); ?>
                                        </span>
                                    </td>

                                    <!-- Price -->
                                    <td>
                                        <div class="price-stack">
                                            <strong>₹<?php echo number_format($finalPrice, 2); ?></strong>
                                            <?php if ($disc > 0): ?>
                                                <del>₹<?php echo number_format($origPrice, 2); ?></del>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Discount -->
                                    <td>
                                        <?php if ($disc > 0): ?>
                                            <span class="discount-pill">
                                                -<?php echo number_format($disc, 0); ?>% OFF
                                            </span>
                                        <?php else: ?>
                                            <span class="discount-zero">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Stock -->
                                    <td>
                                        <?php if ($stockQty <= 0): ?>
                                            <span class="stock-indicator stock-out">
                                                <span class="stock-dot-indicator"></span> Out of Stock
                                            </span>
                                        <?php elseif ($stockQty <= 5): ?>
                                            <span class="stock-indicator stock-low">
                                                <span class="stock-dot-indicator"></span> Only <?php echo $stockQty; ?> left
                                            </span>
                                        <?php else: ?>
                                            <span class="stock-indicator stock-in">
                                                <span class="stock-dot-indicator"></span> <?php echo $stockQty; ?> in stock
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <span class="status-pill status-<?php echo htmlspecialchars($statusVal); ?>">
                                            <?php echo htmlspecialchars($statusVal); ?>
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td style="text-align: right;">
                                        <div class="actions-group" style="justify-content: flex-end;">
                                            <!-- View on customer store -->
                                            <a 
                                                href="../customer/product_details.php?id=<?php echo $pId; ?>" 
                                                target="_blank" 
                                                class="btn-action-icon" 
                                                title="View on Store"
                                            >
                                                👁
                                            </a>

                                            <!-- Edit Product Button -->
                                            <button 
                                                type="button" 
                                                class="btn-action-icon" 
                                                title="Edit Product"
                                                onclick='openEditModal(<?php echo json_encode([
                                                    "id" => $pId,
                                                    "name" => $prod["name"],
                                                    "category_id" => (int)$prod["category_id"],
                                                    "description" => $prod["description"],
                                                    "price" => $origPrice,
                                                    "discount" => $disc,
                                                    "stock" => $stockQty,
                                                    "status" => $statusVal,
                                                    "image" => $prod["image"],
                                                    "image_url" => $imgUrl
                                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                            >
                                                ✎
                                            </button>

                                            <!-- Delete Product Button -->
                                            <button 
                                                type="button" 
                                                class="btn-action-icon btn-action-delete" 
                                                title="Delete Product"
                                                onclick="openDeleteModal(<?php echo $pId; ?>, '<?php echo addslashes(htmlspecialchars($prod["name"])); ?>')"
                                            >
                                                🗑
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-table-state">
                                        <span>◈</span>
                                        <h4>No Products Found</h4>
                                        <p>No products match your current search query or filter criteria.</p>
                                        <button type="button" class="btn-add-primary" onclick="openAddModal()">
                                            Add First Product
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<!-- =========================================================
     ADD PRODUCT MODAL
========================================================= -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <form method="POST" action="products.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_product">

            <div class="modal-header">
                <h2>Add New Product</h2>
                <button type="button" class="modal-close" onclick="closeModal('addModal')">✕</button>
            </div>

            <div class="modal-body">
                <!-- Name -->
                <div class="form-group">
                    <label class="form-label">Product Name <span class="req">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Wireless Noise-Cancelling Headphones" required>
                </div>

                <!-- Category & Status -->
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Category <span class="req">*</span></label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat["id"]; ?>">
                                    <?php echo htmlspecialchars($cat["name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" placeholder="Enter key features, specifications, and product details..."></textarea>
                </div>

                <!-- Price, Discount, Stock -->
                <div class="form-grid-2" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Price (₹) <span class="req">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control" placeholder="0.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Discount (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount" class="form-control" placeholder="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Stock Quantity <span class="req">*</span></label>
                        <input type="number" min="0" name="stock" class="form-control" placeholder="10" required>
                    </div>
                </div>

                <!-- Product Image Upload -->
                <div class="form-group">
                    <label class="form-label">Product Image</label>
                    <div class="image-upload-wrapper" id="addDropzone">
                        <input type="file" name="image" id="addImageInput" accept="image/jpeg,image/png,image/webp" onchange="handleImagePreview(this, 'addPreviewBox', 'addPreviewImg', 'addPreviewName', 'addPreviewSize')">
                        <span class="upload-icon">📁</span>
                        <div class="upload-text">
                            <strong>Choose an image or drag & drop here</strong>
                            <small>Supports JPG, JPEG, PNG, WEBP (Max 5MB)</small>
                            <small style="color: #6258ff; margin-top: 4px;">If left blank, a professional default placeholder will be displayed.</small>
                        </div>
                    </div>

                    <!-- Live Image Preview Strip -->
                    <div class="image-preview-strip" id="addPreviewBox" style="display: none;">
                        <div class="preview-img-box">
                            <img src="" id="addPreviewImg" alt="Preview">
                        </div>
                        <div class="preview-meta">
                            <strong id="addPreviewName">filename.jpg</strong>
                            <small id="addPreviewSize">0 KB</small>
                        </div>
                        <button type="button" class="btn-action-icon btn-action-delete" title="Remove preview" onclick="clearImagePreview('addImageInput', 'addPreviewBox')">✕</button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn-modal-save">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================
     EDIT PRODUCT MODAL
========================================================= -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <form method="POST" action="products.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="product_id" id="editProductId">

            <div class="modal-header">
                <h2>Edit Product</h2>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">✕</button>
            </div>

            <div class="modal-body">
                <!-- Name -->
                <div class="form-group">
                    <label class="form-label">Product Name <span class="req">*</span></label>
                    <input type="text" name="name" id="editName" class="form-control" required>
                </div>

                <!-- Category & Status -->
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Category <span class="req">*</span></label>
                        <select name="category_id" id="editCategoryId" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat["id"]; ?>">
                                    <?php echo htmlspecialchars($cat["name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="editStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="editDescription" class="form-control"></textarea>
                </div>

                <!-- Price, Discount, Stock -->
                <div class="form-grid-2" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Price (₹) <span class="req">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" id="editPrice" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Discount (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount" id="editDiscount" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Stock Quantity <span class="req">*</span></label>
                        <input type="number" min="0" name="stock" id="editStock" class="form-control" required>
                    </div>
                </div>

                <!-- Current Product Image Preview & Delete Option -->
                <div class="form-group">
                    <label class="form-label">Current Product Image</label>
                    <div class="current-image-preview" id="editCurrentImageBlock">
                        <img src="" id="editCurrentImageTag" alt="Current Image">
                        <div style="flex: 1;">
                            <span id="editCurrentImageLabel" style="font-weight: 700; font-size: 13px; color: #171927; display: block;">No image assigned</span>
                            <small id="editCurrentImageSubtext" style="color: #8c93a8; font-size: 11px;">Default placeholder currently displayed.</small>
                        </div>
                        <label id="editDeleteImageCheckboxContainer" style="display: none; align-items: center; gap: 6px; font-size: 12px; color: #e53e3e; cursor: pointer; font-weight: 700;">
                            <input type="checkbox" name="delete_image" value="1" id="editDeleteImageCheckbox">
                            Remove Image
                        </label>
                    </div>
                </div>

                <!-- Replace Image Upload Field -->
                <div class="form-group">
                    <label class="form-label">Replace / Upload New Image</label>
                    <div class="image-upload-wrapper" id="editDropzone">
                        <input type="file" name="image" id="editImageInput" accept="image/jpeg,image/png,image/webp" onchange="handleImagePreview(this, 'editPreviewBox', 'editPreviewImg', 'editPreviewName', 'editPreviewSize')">
                        <span class="upload-icon">🔄</span>
                        <div class="upload-text">
                            <strong>Click to select new image or drag here</strong>
                            <small>Leave empty to keep existing image</small>
                            <small>Supports JPG, JPEG, PNG, WEBP (Max 5MB)</small>
                        </div>
                    </div>

                    <!-- New live replacement preview -->
                    <div class="image-preview-strip" id="editPreviewBox" style="display: none;">
                        <div class="preview-img-box">
                            <img src="" id="editPreviewImg" alt="New Preview">
                        </div>
                        <div class="preview-meta">
                            <strong id="editPreviewName">new_file.jpg</strong>
                            <small id="editPreviewSize">0 KB</small>
                        </div>
                        <button type="button" class="btn-action-icon btn-action-delete" title="Cancel replacement" onclick="clearImagePreview('editImageInput', 'editPreviewBox')">✕</button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-modal-save">Update Product</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================
     DELETE CONFIRMATION MODAL
========================================================= -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box confirm-dialog">
        <form method="POST" action="products.php">
            <input type="hidden" name="action" value="delete_product">
            <input type="hidden" name="product_id" id="deleteProductId">

            <div class="confirm-icon">🗑</div>
            <h3>Delete Product?</h3>
            <p>
                Are you sure you want to delete <strong id="deleteProductName">this product</strong>?<br>
                Its associated uploaded image will also be removed. This action cannot be undone.
            </p>

            <div class="confirm-dialog-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn-confirm-delete">Yes, Delete Product</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================
     MODAL & PREVIEW JAVASCRIPT
========================================================= -->
<script>
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function openEditModal(prod) {
        document.getElementById('editProductId').value = prod.id;
        document.getElementById('editName').value = prod.name;
        document.getElementById('editCategoryId').value = prod.category_id;
        document.getElementById('editDescription').value = prod.description || '';
        document.getElementById('editPrice').value = prod.price;
        document.getElementById('editDiscount').value = prod.discount;
        document.getElementById('editStock').value = prod.stock;
        document.getElementById('editStatus').value = prod.status;

        // Current image representation
        const currentImgTag = document.getElementById('editCurrentImageTag');
        const currentImgLabel = document.getElementById('editCurrentImageLabel');
        const currentImgSubtext = document.getElementById('editCurrentImageSubtext');
        const deleteCheckboxContainer = document.getElementById('editDeleteImageCheckboxContainer');
        const deleteCheckbox = document.getElementById('editDeleteImageCheckbox');

        deleteCheckbox.checked = false;
        currentImgTag.src = prod.image_url;

        if (prod.image && prod.image.trim() !== '') {
            currentImgLabel.textContent = prod.image;
            currentImgSubtext.textContent = 'Uploaded custom product image.';
            deleteCheckboxContainer.style.display = 'inline-flex';
        } else {
            currentImgLabel.textContent = 'No custom image';
            currentImgSubtext.textContent = 'Using QARTIV default product placeholder.';
            deleteCheckboxContainer.style.display = 'none';
        }

        // Clear any previous new image preview
        clearImagePreview('editImageInput', 'editPreviewBox');

        document.getElementById('editModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function openDeleteModal(productId, productName) {
        document.getElementById('deleteProductId').value = productId;
        document.getElementById('deleteProductName').textContent = '"' + productName + '"';
        document.getElementById('deleteModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
        }
        document.body.style.overflow = 'auto';
    }

    // Close modal on click outside box
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
            document.body.style.overflow = 'auto';
        }
    });

    // Live Image Preview Functionality
    function handleImagePreview(input, boxId, imgId, nameId, sizeId) {
        const file = input.files && input.files[0];
        const previewBox = document.getElementById(boxId);
        const previewImg = document.getElementById(imgId);
        const previewName = document.getElementById(nameId);
        const previewSize = document.getElementById(sizeId);

        if (!file) {
            previewBox.style.display = 'none';
            return;
        }

        // Format file size
        let sizeStr = '';
        if (file.size > 1024 * 1024) {
            sizeStr = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
        } else {
            sizeStr = (file.size / 1024).toFixed(1) + ' KB';
        }

        previewName.textContent = file.name;
        previewSize.textContent = sizeStr;

        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewBox.style.display = 'flex';
        };
        reader.readAsDataURL(file);
    }

    function clearImagePreview(inputId, boxId) {
        const input = document.getElementById(inputId);
        const box = document.getElementById(boxId);
        if (input) input.value = '';
        if (box) box.style.display = 'none';
    }

    // Drag and Drop styling
    ['addDropzone', 'editDropzone'].forEach(id => {
        const dropzone = document.getElementById(id);
        if (!dropzone) return;

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
    });
</script>

</body>
</html>
