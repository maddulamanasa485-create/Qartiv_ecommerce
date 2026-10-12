<?php
session_start();
require_once "../config/db.php";
require_once "../config/helpers.php";

/* =========================================================
   USER ACCESS STATE (Guest, Customer, or Admin)
========================================================= */
$isLoggedIn = isset($_SESSION["user_id"]);
$userRole   = $_SESSION["user_role"] ?? "";
$userId     = (int)($_SESSION["user_id"] ?? 0);
$userName   = $_SESSION["user_name"] ?? ($isLoggedIn ? "Customer" : "Guest");

$message = "";
$messageType = "";

/* =========================================================
   FETCH PRODUCT
========================================================= */
$productId = (int)($_GET["id"] ?? 0);

if ($productId <= 0) {
    header("Location: products.php");
    exit;
}

/* =========================================================
   HANDLE ADD TO CART
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_to_cart"])) {
    if (!$isLoggedIn) {
        header("Location: ../auth/login.php?msg=auth_required");
        exit;
    }
    $reqQty = max(1, (int)($_POST["quantity"] ?? 1));

    $stockCheckStmt = $conn->prepare(
        "SELECT id, name, stock, price, discount
         FROM products
         WHERE id = ? AND status = 'active'
         LIMIT 1"
    );

    if ($stockCheckStmt) {
        $stockCheckStmt->bind_param("i", $productId);
        $stockCheckStmt->execute();
        $prodData = $stockCheckStmt->get_result()->fetch_assoc();
        $stockCheckStmt->close();

        if ($prodData) {
            $availStock = (int)$prodData["stock"];

            if ($availStock > 0) {
                // Check existing cart item
                $cartStmt = $conn->prepare(
                    "SELECT id, quantity
                     FROM cart
                     WHERE user_id = ? AND product_id = ?
                     LIMIT 1"
                );
                $cartStmt->bind_param("ii", $userId, $productId);
                $cartStmt->execute();
                $existingCart = $cartStmt->get_result()->fetch_assoc();
                $cartStmt->close();

                if ($existingCart) {
                    $currentQty = (int)$existingCart["quantity"];
                    $newQty = $currentQty + $reqQty;

                    if ($newQty <= $availStock) {
                        $updCart = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                        $updCart->bind_param("iii", $newQty, $userId, $productId);
                        $updCart->execute();
                        $updCart->close();

                        $message = "Updated quantity to {$newQty} in your cart.";
                        $messageType = "success";
                    } else {
                        $maxAddable = $availStock - $currentQty;
                        if ($maxAddable > 0) {
                            $updCart = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                            $updCart->bind_param("iii", $availStock, $userId, $productId);
                            $updCart->execute();
                            $updCart->close();
                            $message = "Added {$maxAddable} more item(s). You have the maximum available stock ({$availStock}) in your cart.";
                            $messageType = "success";
                        } else {
                            $message = "You already have the maximum available stock ({$availStock}) in your cart.";
                            $messageType = "error";
                        }
                    }
                } else {
                    $insertQty = min($reqQty, $availStock);
                    $insCart = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $insCart->bind_param("iii", $userId, $productId, $insertQty);
                    if ($insCart->execute()) {
                        $message = "Added {$insertQty} item(s) of " . htmlspecialchars($prodData["name"]) . " to your cart.";
                        $messageType = "success";
                    } else {
                        $message = "Failed to add product to cart.";
                        $messageType = "error";
                    }
                    $insCart->close();
                }
            } else {
                $message = "This product is currently out of stock.";
                $messageType = "error";
            }
        } else {
            $message = "Product not found or inactive.";
            $messageType = "error";
        }
    }
}

/* =========================================================
   HANDLE WISHLIST TOGGLE
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_wishlist"])) {
    if (!$isLoggedIn) {
        header("Location: ../auth/login.php?msg=auth_required");
        exit;
    }
    $wishCheck = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1");
    $wishCheck->bind_param("ii", $userId, $productId);
    $wishCheck->execute();
    $wishExists = $wishCheck->get_result()->fetch_assoc();
    $wishCheck->close();

    if ($wishExists) {
        $delWish = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $delWish->bind_param("ii", $userId, $productId);
        $delWish->execute();
        $delWish->close();
        $message = "Removed from your wishlist.";
        $messageType = "success";
    } else {
        $insWish = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $insWish->bind_param("ii", $userId, $productId);
        $insWish->execute();
        $insWish->close();
        $message = "Added to your wishlist.";
        $messageType = "success";
    }
}

/* =========================================================
   HANDLE REVIEW SUBMISSION
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_review"])) {
    if (!$isLoggedIn) {
        header("Location: ../auth/login.php?msg=auth_required");
        exit;
    }
    $rating = max(1, min(5, (int)($_POST["rating"] ?? 5)));
    $comment = trim($_POST["comment"] ?? "");

    if (empty($comment)) {
        $message = "Please enter a comment for your review.";
        $messageType = "error";
    } else {
        $rIns = $conn->prepare("INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
        $rIns->bind_param("iiis", $userId, $productId, $rating, $comment);
        if ($rIns->execute()) {
            $message = "Thank you! Your verified product review has been submitted.";
            $messageType = "success";
        } else {
            $message = "Unable to save review. Please try again.";
            $messageType = "error";
        }
        $rIns->close();
    }
}

/* =========================================================
   QUERY MAIN PRODUCT
========================================================= */
$stmt = $conn->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p
     INNER JOIN categories c ON p.category_id = c.id
     WHERE p.id = ? AND p.status = 'active'
     LIMIT 1"
);
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: products.php");
    exit;
}

$price = (float)$product["price"];
$discount = (float)$product["discount"];
$finalPrice = calculateSalePrice($price, $discount);
$savings = max(0, $price - $finalPrice);
$stock = (int)$product["stock"];
$imageUrl = getProductImageUrl($product["image"], "../");

/* =========================================================
   CART & WISHLIST COUNTS
========================================================= */
$cartCount = 0;
$wishlistCount = 0;
$isWishlisted = false;

if ($isLoggedIn && $userId > 0) {
    $cStmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS total FROM cart WHERE user_id = ?");
    if ($cStmt) {
        $cStmt->bind_param("i", $userId);
        $cStmt->execute();
        $cRes = $cStmt->get_result()->fetch_assoc();
        $cartCount = (int)($cRes["total"] ?? 0);
        $cStmt->close();
    }

    $wStmt = $conn->prepare("SELECT COUNT(*) AS total FROM wishlist WHERE user_id = ?");
    if ($wStmt) {
        $wStmt->bind_param("i", $userId);
        $wStmt->execute();
        $wRes = $wStmt->get_result()->fetch_assoc();
        $wishlistCount = (int)($wRes["total"] ?? 0);
        $wStmt->close();
    }

    $wCheckStmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1");
    if ($wCheckStmt) {
        $wCheckStmt->bind_param("ii", $userId, $productId);
        $wCheckStmt->execute();
        if ($wCheckStmt->get_result()->num_rows > 0) {
            $isWishlisted = true;
        }
        $wCheckStmt->close();
    }
}

/* =========================================================
   RELATED PRODUCTS
========================================================= */
$relatedProducts = [];
$relStmt = $conn->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p
     INNER JOIN categories c ON p.category_id = c.id
     WHERE p.status = 'active' AND p.id != ? AND p.category_id = ?
     ORDER BY p.id DESC
     LIMIT 4"
);
if ($relStmt) {
    $relStmt->bind_param("ii", $productId, $product["category_id"]);
    $relStmt->execute();
    $relRes = $relStmt->get_result();
    while ($rRow = $relRes->fetch_assoc()) {
        $relatedProducts[] = $rRow;
    }
    $relStmt->close();
}

// Fallback if less than 4 related products
if (count($relatedProducts) < 4) {
    $needed = 4 - count($relatedProducts);
    $fallbackStmt = $conn->prepare(
        "SELECT p.*, c.name AS category_name
         FROM products p
         INNER JOIN categories c ON p.category_id = c.id
         WHERE p.status = 'active' AND p.id != ?
         ORDER BY p.id DESC
         LIMIT ?"
    );
    if ($fallbackStmt) {
        $fallbackStmt->bind_param("ii", $productId, $needed);
        $fallbackStmt->execute();
        $fbRes = $fallbackStmt->get_result();
        while ($fbRow = $fbRes->fetch_assoc()) {
            // Avoid duplicate
            $already = false;
            foreach ($relatedProducts as $rp) {
                if ($rp["id"] == $fbRow["id"]) { $already = true; break; }
            }
            if (!$already) {
                $relatedProducts[] = $fbRow;
            }
        }
        $fallbackStmt->close();
    }
}

/* =========================================================
   QUERY REVIEWS FOR THIS PRODUCT
========================================================= */
$reviews = [];
$rStmt = $conn->prepare("
    SELECT r.*, u.name AS reviewer_name
    FROM reviews r
    INNER JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ?
    ORDER BY r.id DESC
");
$rStmt->bind_param("i", $productId);
$rStmt->execute();
$rRes = $rStmt->get_result();
$ratingSum = 0;
while ($rw = $rRes->fetch_assoc()) {
    $reviews[] = $rw;
    $ratingSum += (int)$rw['rating'];
}
$rStmt->close();

$totalReviewsCount = count($reviews);
$avgRating = $totalReviewsCount > 0 ? round($ratingSum / $totalReviewsCount, 1) : 4.9;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product["name"]); ?> | QARTIV</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', Inter, -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f7f8fc;
            color: #171a38;
            min-height: 100vh;
        }

        /* Container */
        .details-wrapper {
            max-width: 1280px;
            margin: 0 auto;
            padding: 30px 24px 60px;
        }

        /* Breadcrumb */
        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #8c93a8;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .breadcrumb-nav a {
            color: #6258ff;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb-nav a:hover {
            color: #4b3fd6;
        }

        .breadcrumb-sep {
            color: #c4c9d8;
        }

        /* Message Banner */
        .details-alert {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .details-alert.success {
            background: #eafaf1;
            color: #187948;
            border: 1px solid #b7ebd0;
        }

        .details-alert.error {
            background: #fff1f2;
            color: #b91c1c;
            border: 1px solid #fed7d7;
        }

        /* Main Product Section */
        .product-showcase-card {
            background: #ffffff;
            border-radius: 28px;
            border: 1px solid #e7e9f1;
            box-shadow: 0 12px 40px rgba(25, 29, 70, 0.05);
            padding: 40px;
            display: grid;
            grid-template-columns: 1fr 1.15fr;
            gap: 48px;
            margin-bottom: 50px;
        }

        /* Left Column: Image Showcase */
        .showcase-gallery {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .large-image-frame {
            position: relative;
            width: 100%;
            height: 480px;
            background: radial-gradient(circle at 50% 40%, #f3f1ff, #f7f8fc 70%);
            border-radius: 24px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e9ecf4;
            box-shadow: 0 8px 30px rgba(98, 88, 255, 0.06);
        }

        .large-image-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .large-image-frame:hover img {
            transform: scale(1.04);
        }

        .badge-discount-float {
            position: absolute;
            top: 20px;
            left: 20px;
            background: linear-gradient(135deg, #6258ff, #7c4eea);
            color: #ffffff;
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 900;
            box-shadow: 0 6px 16px rgba(98, 88, 255, 0.35);
            z-index: 2;
        }

        .badge-stock-float {
            position: absolute;
            bottom: 20px;
            left: 20px;
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 800;
            z-index: 2;
        }

        .badge-stock-in {
            background: rgba(234, 250, 241, 0.95);
            color: #187948;
            border: 1px solid #b7ebd0;
        }

        .badge-stock-low {
            background: rgba(255, 248, 235, 0.95);
            color: #b7791f;
            border: 1px solid #fbd38d;
        }

        .badge-stock-out {
            background: rgba(255, 241, 242, 0.95);
            color: #e53e3e;
            border: 1px solid #feb2b2;
        }

        /* Trust Badges Strip */
        .trust-guarantee-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 8px;
        }

        .trust-badge-item {
            background: #fafbfe;
            border: 1px solid #eef0f7;
            border-radius: 14px;
            padding: 14px 10px;
            text-align: center;
        }

        .trust-badge-item .t-icon {
            font-size: 20px;
            display: block;
            margin-bottom: 4px;
        }

        .trust-badge-item strong {
            display: block;
            font-size: 11px;
            color: #1a1e36;
            font-weight: 800;
        }

        .trust-badge-item small {
            display: block;
            color: #8c93a8;
            font-size: 9px;
        }

        /* Right Column: Details Info */
        .showcase-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .category-pill {
            display: inline-block;
            background: #f1f0ff;
            color: #6258ff;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: fit-content;
            margin-bottom: 14px;
        }

        .product-title {
            font-size: 32px;
            font-weight: 900;
            color: #171927;
            line-height: 1.25;
            margin-bottom: 12px;
        }

        /* Ratings Placeholder */
        .rating-box {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
        }

        .rating-stars {
            color: #f59e0b;
            font-size: 17px;
            letter-spacing: 2px;
        }

        .rating-score {
            font-weight: 800;
            font-size: 13px;
            color: #171927;
        }

        .rating-count {
            color: #8c93a8;
            font-size: 12px;
        }

        .rating-divider {
            color: #d1d5db;
        }

        /* Price Stack */
        .pricing-box {
            background: #fcfdfe;
            border: 1px solid #edf0f8;
            border-radius: 20px;
            padding: 22px 26px;
            margin-bottom: 24px;
        }

        .price-main-row {
            display: flex;
            align-items: baseline;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }

        .sale-price-large {
            font-size: 36px;
            font-weight: 900;
            color: #171927;
            letter-spacing: -0.5px;
        }

        .original-price-strike {
            font-size: 18px;
            color: #9ba1b5;
            text-decoration: line-through;
            font-weight: 600;
        }

        .save-badge-pill {
            background: #eafaf1;
            color: #187948;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
        }

        .tax-note {
            color: #8c93a8;
            font-size: 11px;
            font-weight: 600;
        }

        /* Stock Status Line */
        .stock-status-line {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            font-size: 13px;
            font-weight: 800;
        }

        .stock-dot-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        /* Description */
        .description-section {
            margin-bottom: 28px;
        }

        .description-section h4 {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #7b8298;
            margin-bottom: 10px;
        }

        .description-section p {
            color: #4b5269;
            font-size: 14px;
            line-height: 1.7;
        }

        /* Purchase Action Form */
        .purchase-action-form {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        /* Quantity Counter */
        .quantity-controller {
            display: inline-flex;
            align-items: center;
            border: 1px solid #dfe3ee;
            border-radius: 14px;
            background: #ffffff;
            overflow: hidden;
            height: 52px;
        }

        .quantity-controller button {
            width: 44px;
            height: 100%;
            background: #f7f8fc;
            border: none;
            color: #4b5269;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quantity-controller button:hover:not(:disabled) {
            background: #eef0fa;
            color: #6258ff;
        }

        .quantity-controller button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .quantity-controller input {
            width: 50px;
            height: 100%;
            border: none;
            text-align: center;
            font-size: 15px;
            font-weight: 800;
            color: #171927;
            outline: none;
        }

        /* Action Buttons */
        .btn-add-cart-large {
            flex: 1;
            min-width: 200px;
            height: 52px;
            background: linear-gradient(135deg, #6258ff, #7c4eea);
            color: #ffffff;
            border: none;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(98, 88, 255, 0.32);
            transition: all 0.25s ease;
        }

        .btn-add-cart-large:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(98, 88, 255, 0.42);
        }

        .btn-add-cart-large:disabled {
            background: #e2e4ec;
            color: #9ba1b4;
            box-shadow: none;
            cursor: not-allowed;
        }

        .btn-wishlist-toggle {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            border: 1px solid #dfe3ee;
            background: #ffffff;
            color: #636b83;
            font-size: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn-wishlist-toggle:hover {
            border-color: #f43f5e;
            color: #f43f5e;
            background: #fff5f7;
            transform: scale(1.06);
        }

        .btn-wishlist-toggle.active {
            background: #fff0f3;
            border-color: #fda4af;
            color: #e11d48;
        }

        /* Related Products Section */
        .related-products-section {
            margin-top: 40px;
        }

        .related-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .related-heading h3 {
            font-size: 22px;
            font-weight: 900;
            color: #171927;
        }

        .related-heading a {
            color: #6258ff;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        /* Related Product Card styling */
        .rel-card {
            background: #ffffff;
            border: 1px solid #e7e9f1;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(25, 29, 65, 0.035);
            transition: all 0.28s ease;
            display: flex;
            flex-direction: column;
        }

        .rel-card:hover {
            transform: translateY(-5px);
            border-color: #d9d4ff;
            box-shadow: 0 16px 32px rgba(30, 30, 90, 0.09);
        }

        .rel-thumb-box {
            position: relative;
            height: 200px;
            background: #f7f8fc;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rel-thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s ease;
        }

        .rel-card:hover .rel-thumb-box img {
            transform: scale(1.06);
        }

        .rel-info {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .rel-cat {
            color: #6258ff;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
        }

        .rel-name {
            font-size: 14px;
            font-weight: 800;
            color: #171927;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 38px;
        }

        .rel-price-row {
            margin-top: auto;
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 12px;
        }

        .rel-price-row strong {
            font-size: 16px;
            font-weight: 900;
            color: #171927;
        }

        .rel-price-row del {
            font-size: 11px;
            color: #9ba1b4;
        }

        .rel-btn {
            display: block;
            text-align: center;
            background: #f4f5fb;
            color: #3b4256;
            padding: 9px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .rel-btn:hover {
            background: #6258ff;
            color: #ffffff;
        }

        /* Reviews Section Styles */
        .reviews-section {
            background: #ffffff;
            border: 1px solid #ede8de;
            border-radius: 20px;
            padding: 36px;
            margin-top: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        .reviews-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f1ece4;
            padding-bottom: 24px;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .reviews-summary-badge {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .rating-big-num {
            font-size: 38px;
            font-weight: 900;
            color: #1a1e36;
        }

        .rating-stars-gold {
            color: #d4af37;
            font-size: 20px;
            letter-spacing: 2px;
        }

        .review-card-item {
            padding: 20px 0;
            border-bottom: 1px solid #f5f3ee;
        }

        .review-card-item:last-child {
            border-bottom: none;
        }

        .reviewer-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .reviewer-avatar-mini {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #1a1e36;
            color: #d4af37;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .review-comment-text {
            font-size: 14px;
            color: #4b526d;
            line-height: 1.6;
        }

        .review-form-box {
            background: #faf8f5;
            border: 1px solid #ede8de;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 32px;
        }

        /* Responsive */
        @media (max-width: 990px) {
            .product-showcase-card {
                grid-template-columns: 1fr;
                gap: 32px;
                padding: 28px;
            }
            .large-image-frame {
                height: 380px;
            }
            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .details-wrapper {
                padding: 20px 14px 40px;
            }
            .product-showcase-card {
                padding: 20px;
            }
            .large-image-frame {
                height: 290px;
            }
            .product-title {
                font-size: 24px;
            }
            .sale-price-large {
                font-size: 28px;
            }
            .purchase-action-form {
                flex-direction: column;
                align-items: stretch;
            }
            .quantity-controller {
                width: 100%;
                justify-content: center;
            }
            .btn-add-cart-large {
                width: 100%;
            }
            .related-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="qartiv-products-page">

<!-- =========================================================
     TOPBAR NAVIGATION
========================================================= -->
<header class="products-header">
    <div class="products-brand">
        <a href="dashboard.php">
            <div class="products-brand-mark">Q</div>
            <div class="products-brand-text">
                <strong>QARTIV</strong>
                <span>SMART COMMERCE</span>
            </div>
        </a>
    </div>

    <nav class="products-nav">
        <?php if ($userRole === 'admin'): ?>
            <a href="../admin/dashboard.php">Admin Panel</a>
            <a href="products.php" class="active">Products</a>
            <a href="../admin/orders.php">Manage Orders</a>
        <?php elseif ($isLoggedIn): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="products.php" class="active">Products</a>
            <a href="orders.php">My Orders</a>
        <?php else: ?>
            <a href="../index.php">Home</a>
            <a href="products.php" class="active">Collection</a>
            <a href="../auth/login.php">Sign In</a>
        <?php endif; ?>
    </nav>

    <div class="products-header-actions">
        <!-- CART -->
        <a href="<?php echo $isLoggedIn ? 'cart.php' : '../auth/login.php?msg=auth_required'; ?>" class="products-cart-btn">
            <span>🛒</span>
            <span>Cart</span>
            <?php if ($cartCount > 0): ?>
                <b><?php echo $cartCount; ?></b>
            <?php endif; ?>
        </a>

        <!-- WISHLIST -->
        <a href="<?php echo $isLoggedIn ? 'wishlist.php' : '../auth/login.php?msg=auth_required'; ?>" class="products-cart-btn">
            <span>♡</span>
            <span>Wishlist</span>
            <?php if ($wishlistCount > 0): ?>
                <b><?php echo $wishlistCount; ?></b>
            <?php endif; ?>
        </a>

        <?php if ($isLoggedIn): ?>
            <!-- USER AVATAR -->
            <a href="<?php echo $userRole === 'admin' ? '../admin/dashboard.php' : 'account.php'; ?>" class="products-user" style="text-decoration:none;color:inherit;">
                <div class="products-avatar">
                    <?php echo htmlspecialchars(strtoupper(substr($userName, 0, 1))); ?>
                </div>
                <span><?php echo htmlspecialchars($userName); ?></span>
            </a>
            <a href="../auth/logout.php" style="font-size:12px;color:#ef4444;font-weight:700;margin-left:8px;text-decoration:none;">Logout</a>
        <?php else: ?>
            <a href="../auth/login.php" class="btn-primary" style="padding:8px 16px;font-size:13px;border-radius:8px;background:var(--primary,#202b6b);color:#fff;text-decoration:none;font-weight:700;">Sign In</a>
            <a href="../auth/register.php" style="padding:8px 16px;font-size:13px;border:1px solid #d4af37;color:#b38a1e;border-radius:8px;font-weight:700;text-decoration:none;">Register</a>
        <?php endif; ?>
    </div>
</header>

<!-- =========================================================
     MAIN DETAILS CONTAINER
========================================================= -->
<main class="details-wrapper">

    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav">
        <a href="dashboard.php">Home</a>
        <span class="breadcrumb-sep">›</span>
        <a href="products.php">Products</a>
        <span class="breadcrumb-sep">›</span>
        <a href="products.php?category=<?php echo (int)$product["category_id"]; ?>">
            <?php echo htmlspecialchars($product["category_name"]); ?>
        </a>
        <span class="breadcrumb-sep">›</span>
        <span><?php echo htmlspecialchars($product["name"]); ?></span>
    </nav>

    <!-- Message Notification -->
    <?php if ($message !== ""): ?>
        <div class="details-alert <?php echo htmlspecialchars($messageType); ?>">
            <span><?php echo $messageType === "success" ? "✓" : "⚠"; ?></span>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Main Showcase Card -->
    <section class="product-showcase-card">

        <!-- Left: Image Showcase -->
        <div class="showcase-gallery">
            <div class="large-image-frame">
                <!-- Floating Discount Badge -->
                <?php if ($discount > 0): ?>
                    <span class="badge-discount-float">
                        -<?php echo number_format($discount, 0); ?>% OFF
                    </span>
                <?php endif; ?>

                <!-- Floating Stock Availability Badge -->
                <?php if ($stock <= 0): ?>
                    <span class="badge-stock-float badge-stock-out">✕ Sold Out</span>
                <?php elseif ($stock <= 5): ?>
                    <span class="badge-stock-float badge-stock-low">▲ Only <?php echo $stock; ?> Left</span>
                <?php else: ?>
                    <span class="badge-stock-float badge-stock-in">✓ In Stock</span>
                <?php endif; ?>

                <!-- Large Responsive Product Image -->
                <img 
                    src="<?php echo htmlspecialchars($imageUrl); ?>" 
                    alt="<?php echo htmlspecialchars($product["name"]); ?>" 
                    id="mainProductImage"
                    loading="lazy"
                >
            </div>

            <!-- Trust / Guarantee Strip -->
            <div class="trust-guarantee-row">
                <div class="trust-badge-item">
                    <span class="t-icon">🔒</span>
                    <strong>100% Genuine</strong>
                    <small>Direct from Qartiv</small>
                </div>
                <div class="trust-badge-item">
                    <span class="t-icon">⚡</span>
                    <strong>Express Delivery</strong>
                    <small>Fast & Tracked</small>
                </div>
                <div class="trust-badge-item">
                    <span class="t-icon">↺</span>
                    <strong>7-Day Returns</strong>
                    <small>Hassle-Free Policy</small>
                </div>
            </div>
        </div>

        <!-- Right: Product Information & Purchase -->
        <div class="showcase-info">
            <span class="category-pill">
                <?php echo htmlspecialchars($product["category_name"]); ?>
            </span>

            <h1 class="product-title">
                <?php echo htmlspecialchars($product["name"]); ?>
            </h1>

            <!-- Ratings Placeholder -->
            <div class="rating-box">
                <div class="rating-stars">★★★★★</div>
                <span class="rating-score">4.8</span>
                <span class="rating-divider">·</span>
                <span class="rating-count">142 verified ratings</span>
            </div>

            <!-- Pricing Box -->
            <div class="pricing-box">
                <div class="price-main-row">
                    <span class="sale-price-large">
                        ₹<?php echo number_format($finalPrice, 2); ?>
                    </span>

                    <?php if ($discount > 0): ?>
                        <span class="original-price-strike">
                            ₹<?php echo number_format($price, 2); ?>
                        </span>
                        <span class="save-badge-pill">
                            Save ₹<?php echo number_format($savings, 2); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="tax-note">
                    Inclusive of all applicable GST & taxes. Free shipping on orders above ₹999.
                </div>
            </div>

            <!-- Stock Availability Line -->
            <div class="stock-status-line">
                <?php if ($stock > 5): ?>
                    <span class="stock-dot-indicator" style="background: #187948;"></span>
                    <span style="color: #187948;">In Stock — Available for immediate dispatch</span>
                <?php elseif ($stock > 0): ?>
                    <span class="stock-dot-indicator" style="background: #b7791f;"></span>
                    <span style="color: #b7791f;">Limited Stock — Only <?php echo $stock; ?> units available</span>
                <?php else: ?>
                    <span class="stock-dot-indicator" style="background: #e53e3e;"></span>
                    <span style="color: #e53e3e;">Currently Out of Stock — Check back soon</span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="description-section">
                <h4>Product Overview</h4>
                <p>
                    <?php
                    $desc = trim($product["description"] ?? "");
                    echo nl2br(htmlspecialchars($desc !== "" ? $desc : "Experience superior quality and design with this handpicked QARTIV product. Built with premium materials to ensure durability, comfort, and satisfaction for everyday use."));
                    ?>
                </p>
            </div>

            <!-- Purchase Controls Form -->
            <form method="POST" action="product_details.php?id=<?php echo $productId; ?>" class="purchase-action-form">
                <?php if ($stock > 0): ?>
                    <!-- Quantity Selector -->
                    <div class="quantity-controller">
                        <button type="button" onclick="decrementQty()">−</button>
                        <input type="number" id="qtyInput" name="quantity" value="1" min="1" max="<?php echo $stock; ?>" readonly>
                        <button type="button" onclick="incrementQty(<?php echo $stock; ?>)">+</button>
                    </div>

                    <!-- Add to Cart -->
                    <button type="submit" name="add_to_cart" class="btn-add-cart-large">
                        <span>🛒</span>
                        Add to Cart
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-add-cart-large" disabled>
                        Out of Stock
                    </button>
                <?php endif; ?>

                <!-- Wishlist Toggle Button -->
                <button 
                    type="submit" 
                    name="toggle_wishlist" 
                    class="btn-wishlist-toggle <?php echo $isWishlisted ? 'active' : ''; ?>"
                    title="<?php echo $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>"
                >
                    <?php echo $isWishlisted ? '♥' : '♡'; ?>
                </button>
            </form>
        </div>

    </section>

    <!-- =========================================================
         CUSTOMER REVIEWS SECTION
    ========================================================= -->
    <section class="reviews-section">
        <div class="reviews-header">
            <div>
                <span style="color: #d4af37; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; display: block; margin-bottom: 4px;">Verified Customer Feedback</span>
                <h3 style="margin: 0; font-size: 22px; font-weight: 850; color: #1a1e36;">Ratings & Reviews</h3>
            </div>

            <div class="reviews-summary-badge">
                <div class="rating-big-num"><?php echo number_format($avgRating, 1); ?></div>
                <div>
                    <div class="rating-stars-gold">★★★★★</div>
                    <small style="color: #8c90a4; font-weight: 600;">Based on <?php echo $totalReviewsCount; ?> verified review<?php echo $totalReviewsCount !== 1 ? 's' : ''; ?></small>
                </div>
            </div>
        </div>

        <!-- Write a Review Form -->
        <div class="review-form-box">
            <h4 style="margin: 0 0 14px; font-size: 15px; font-weight: 800; color: #1a1e36;">Leave a Review for this Product</h4>
            <form method="POST">
                <input type="hidden" name="submit_review" value="1">
                <div style="display: flex; gap: 16px; margin-bottom: 12px; align-items: center; flex-wrap: wrap;">
                    <label style="font-size: 13px; font-weight: 700; color: #4b526d;">Your Rating:</label>
                    <select name="rating" style="padding: 6px 12px; border: 1px solid #dcdfe8; border-radius: 8px; font-weight: 700; font-size: 13px; color: #1a1e36; outline: none;">
                        <option value="5">★★★★★ (5 - Excellent)</option>
                        <option value="4">★★★★☆ (4 - Very Good)</option>
                        <option value="3">★★★☆☆ (3 - Average)</option>
                        <option value="2">★★☆☆☆ (2 - Poor)</option>
                        <option value="1">★☆☆☆☆ (1 - Terrible)</option>
                    </select>
                </div>

                <div style="margin-bottom: 14px;">
                    <textarea 
                        name="comment" 
                        rows="3" 
                        placeholder="Share your thoughts on performance, aesthetics, and overall quality..."
                        required
                        style="width: 100%; box-sizing: border-box; padding: 12px; border: 1px solid #dcdfe8; border-radius: 10px; font-family: inherit; font-size: 13px; outline: none;"></textarea>
                </div>

                <button type="submit" style="background: #1a1e36; color: #ffffff; border: none; padding: 10px 22px; border-radius: 9px; font-size: 13px; font-weight: 800; cursor: pointer;">
                    Submit Verified Review
                </button>
            </form>
        </div>

        <!-- Reviews List -->
        <div class="reviews-list">
            <?php if (empty($reviews)): ?>
                <p style="color: #8c90a4; font-size: 13px; text-align: center; padding: 20px 0;">No reviews yet for this product. Be the first to share your experience!</p>
            <?php else: ?>
                <?php foreach ($reviews as $rev): ?>
                    <div class="review-card-item">
                        <div class="reviewer-meta">
                            <div class="reviewer-avatar-mini">
                                <?php echo strtoupper(substr($rev['reviewer_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong style="font-size: 14px; color: #1a1e36;"><?php echo htmlspecialchars($rev['reviewer_name']); ?></strong>
                                <span style="display: inline-block; margin-left: 8px; font-size: 11px; background: #ecfdf5; color: #047857; padding: 2px 7px; border-radius: 10px; font-weight: 700;">✓ Verified Buyer</span>
                                <small style="display: block; color: #9ba1b4; font-size: 11px; margin-top: 2px;"><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></small>
                            </div>
                            <div style="margin-left: auto; color: #d4af37; font-size: 14px; letter-spacing: 1px;">
                                <?php echo str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']); ?>
                            </div>
                        </div>
                        <div class="review-comment-text">
                            <?php echo nl2br(htmlspecialchars($rev['comment'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- =========================================================
         RELATED PRODUCTS SECTION
    ========================================================= -->
    <?php if (!empty($relatedProducts)): ?>
        <section class="related-products-section">
            <div class="related-heading">
                <div>
                    <span style="color: #6258ff; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; display: block; margin-bottom: 4px;">Recommended</span>
                    <h3>Similar Products You May Like</h3>
                </div>
                <a href="products.php?category=<?php echo (int)$product["category_id"]; ?>">
                    Explore Category →
                </a>
            </div>

            <div class="related-grid">
                <?php foreach ($relatedProducts as $rel): ?>
                    <?php
                        $rOrig = (float)$rel["price"];
                        $rDisc = (float)$rel["discount"];
                        $rSale = calculateSalePrice($rOrig, $rDisc);
                        $rImg = getProductImageUrl($rel["image"], "../");
                    ?>
                    <article class="rel-card">
                        <a href="product_details.php?id=<?php echo (int)$rel["id"]; ?>" class="rel-thumb-box">
                            <img src="<?php echo htmlspecialchars($rImg); ?>" alt="<?php echo htmlspecialchars($rel["name"]); ?>" loading="lazy">
                        </a>

                        <div class="rel-info">
                            <span class="rel-cat"><?php echo htmlspecialchars($rel["category_name"]); ?></span>
                            <a href="product_details.php?id=<?php echo (int)$rel["id"]; ?>" style="text-decoration: none;">
                                <h4 class="rel-name"><?php echo htmlspecialchars($rel["name"]); ?></h4>
                            </a>

                            <div class="rel-price-row">
                                <strong>₹<?php echo number_format($rSale, 2); ?></strong>
                                <?php if ($rDisc > 0): ?>
                                    <del>₹<?php echo number_format($rOrig, 2); ?></del>
                                <?php endif; ?>
                            </div>

                            <a href="product_details.php?id=<?php echo (int)$rel["id"]; ?>" class="rel-btn">
                                View Product
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</main>

<!-- =========================================================
     FOOTER
========================================================= -->
<footer class="products-footer">
    <div>
        <strong>QARTIV</strong>
        <span>Smart E-Commerce Platform</span>
    </div>
    <p>© <?php echo date("Y"); ?> Qartiv. All rights reserved.</p>
</footer>

<script>
    function decrementQty() {
        const input = document.getElementById('qtyInput');
        let current = parseInt(input.value, 10) || 1;
        if (current > 1) {
            input.value = current - 1;
        }
    }

    function incrementQty(maxStock) {
        const input = document.getElementById('qtyInput');
        let current = parseInt(input.value, 10) || 1;
        if (current < maxStock) {
            input.value = current + 1;
        }
    }
</script>

</body>
</html>
