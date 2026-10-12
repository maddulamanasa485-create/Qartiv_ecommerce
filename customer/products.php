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
   SEARCH + CATEGORY + PRICE + SORT FILTERS
========================================================= */

$search = trim($_GET["search"] ?? "");
$categoryId = (int) ($_GET["category"] ?? 0);
$minPrice = isset($_GET["min_price"]) && is_numeric($_GET["min_price"]) && $_GET["min_price"] > 0 ? (float)$_GET["min_price"] : 0;
$maxPrice = isset($_GET["max_price"]) && is_numeric($_GET["max_price"]) && $_GET["max_price"] > 0 ? (float)$_GET["max_price"] : 0;
$sort = trim($_GET["sort"] ?? "newest");
if (!in_array($sort, ['newest', 'price_asc', 'price_desc', 'rating'], true)) {
    $sort = 'newest';
}


/* =========================================================
   ADD TO CART
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_to_cart"])) {

    if (!$isLoggedIn) {
        header("Location: ../auth/login.php?msg=auth_required");
        exit;
    }

    $productId = (int) ($_POST["product_id"] ?? 0);

    if ($productId > 0) {

        /* Check product */

        $stmt = $conn->prepare(
            "SELECT id, name, stock
             FROM products
             WHERE id = ?
             AND status = 'active'
             LIMIT 1"
        );

        if ($stmt) {

            $stmt->bind_param("i", $productId);
            $stmt->execute();

            $result = $stmt->get_result();
            $product = $result->fetch_assoc();

            $stmt->close();

            if ($product) {

                $stock = (int) $product["stock"];

                if ($stock > 0) {

                    /* Check existing cart item */

                    $cartStmt = $conn->prepare(
                        "SELECT id, quantity
                         FROM cart
                         WHERE user_id = ?
                         AND product_id = ?
                         LIMIT 1"
                    );

                    $cartStmt->bind_param(
                        "ii",
                        $userId,
                        $productId
                    );

                    $cartStmt->execute();

                    $cartResult = $cartStmt->get_result();
                    $existingCart = $cartResult->fetch_assoc();

                    $cartStmt->close();


                    if ($existingCart) {

                        $currentQuantity = (int) $existingCart["quantity"];

                        if ($currentQuantity < $stock) {

                            $newQuantity = $currentQuantity + 1;

                            $updateStmt = $conn->prepare(
                                "UPDATE cart
                                 SET quantity = ?
                                 WHERE user_id = ?
                                 AND product_id = ?"
                            );

                            $updateStmt->bind_param(
                                "iii",
                                $newQuantity,
                                $userId,
                                $productId
                            );

                            $updateStmt->execute();
                            $updateStmt->close();

                            $message = $product["name"] .
                                       " quantity updated in cart.";

                            $messageType = "success";

                        } else {

                            $message =
                                "Maximum available stock already added.";

                            $messageType = "error";
                        }

                    } else {

                        /* Add new product */

                        $insertStmt = $conn->prepare(
                            "INSERT INTO cart
                             (user_id, product_id, quantity)
                             VALUES (?, ?, 1)"
                        );

                        $insertStmt->bind_param(
                            "ii",
                            $userId,
                            $productId
                        );

                        if ($insertStmt->execute()) {

                            $message =
                                $product["name"] .
                                " added to your cart.";

                            $messageType = "success";

                        } else {

                            $message =
                                "Unable to add product to cart.";

                            $messageType = "error";
                        }

                        $insertStmt->close();
                    }

                } else {

                    $message = "This product is currently out of stock.";
                    $messageType = "error";
                }

            } else {

                $message = "Product not found.";
                $messageType = "error";
            }
        }
    }
}


/* =========================================================
   WISHLIST TOGGLE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_wishlist"])) {

    if (!$isLoggedIn) {
        header("Location: ../auth/login.php?msg=auth_required");
        exit;
    }

    $productId = (int) ($_POST["product_id"] ?? 0);

    if ($productId > 0) {

        $checkStmt = $conn->prepare(
            "SELECT id
             FROM wishlist
             WHERE user_id = ?
             AND product_id = ?
             LIMIT 1"
        );

        $checkStmt->bind_param(
            "ii",
            $userId,
            $productId
        );

        $checkStmt->execute();

        $checkResult = $checkStmt->get_result();
        $wishlistItem = $checkResult->fetch_assoc();

        $checkStmt->close();


        if ($wishlistItem) {

            $deleteStmt = $conn->prepare(
                "DELETE FROM wishlist
                 WHERE user_id = ?
                 AND product_id = ?"
            );

            $deleteStmt->bind_param(
                "ii",
                $userId,
                $productId
            );

            $deleteStmt->execute();
            $deleteStmt->close();

            $message = "Removed from wishlist.";
            $messageType = "success";

        } else {

            $insertStmt = $conn->prepare(
                "INSERT INTO wishlist
                 (user_id, product_id)
                 VALUES (?, ?)"
            );

            $insertStmt->bind_param(
                "ii",
                $userId,
                $productId
            );

            if ($insertStmt->execute()) {

                $message = "Added to wishlist.";
                $messageType = "success";

            } else {

                $message = "Unable to update wishlist.";
                $messageType = "error";
            }

            $insertStmt->close();
        }
    }
}


/* =========================================================
   CATEGORY LIST
========================================================= */

$categories = [];

$categoryStmt = $conn->prepare(
    "SELECT id, name
     FROM categories
     WHERE status = 'active'
     ORDER BY name ASC"
);

if ($categoryStmt) {

    $categoryStmt->execute();

    $categoryResult = $categoryStmt->get_result();

    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }

    $categoryStmt->close();
}


/* =========================================================
   PRODUCT QUERY
========================================================= */

$products = [];

$sql = "
    SELECT
        p.id,
        p.category_id,
        p.name,
        p.description,
        p.price,
        p.discount,
        p.stock,
        p.image,
        c.name AS category_name,
        COALESCE((SELECT ROUND(AVG(rating), 1) FROM reviews WHERE product_id = p.id), 4.8) AS avg_rating,
        COALESCE((SELECT COUNT(id) FROM reviews WHERE product_id = p.id), 14) AS review_count
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.id
    WHERE p.status = 'active'
      AND c.status = 'active'
";

$params = [];
$types = "";


/* Search */
if ($search !== "") {
    $sql .= "
        AND (
            p.name LIKE ?
            OR p.description LIKE ?
            OR c.name LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $types .= "sss";
}


/* Category */
if ($categoryId > 0) {
    $sql .= " AND p.category_id = ? ";
    $params[] = $categoryId;
    $types .= "i";
}

/* Price Range */
if ($minPrice > 0) {
    $sql .= " AND (p.price - (p.price * p.discount / 100)) >= ? ";
    $params[] = $minPrice;
    $types .= "d";
}

if ($maxPrice > 0) {
    $sql .= " AND (p.price - (p.price * p.discount / 100)) <= ? ";
    $params[] = $maxPrice;
    $types .= "d";
}

/* Sorting */
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY (p.price - (p.price * p.discount / 100)) ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY (p.price - (p.price * p.discount / 100)) DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY avg_rating DESC, p.id DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.id DESC";
        break;
}


$productStmt = $conn->prepare($sql);

if ($productStmt) {

    if (!empty($params)) {
        $productStmt->bind_param($types, ...$params);
    }

    $productStmt->execute();

    $productResult = $productStmt->get_result();

    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }

    $productStmt->close();
}


/* =========================================================
   CART & WISHLIST COUNTS
========================================================= */

$cartCount = 0;
$wishlistCount = 0;
$wishlistProducts = [];

if ($isLoggedIn && $userId > 0) {
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

    $stmt = $conn->prepare(
        "SELECT product_id
         FROM wishlist
         WHERE user_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $wishlistProducts[] = (int) $row["product_id"];
        }
        $stmt->close();
    }
}


/* =========================================================
   CURRENT FILTER URL
========================================================= */

$currentQuery = "";

if ($search !== "") {
    $currentQuery .= "search=" . urlencode($search);
}

if ($categoryId > 0) {

    if ($currentQuery !== "") {
        $currentQuery .= "&";
    }

    $currentQuery .= "category=" . $categoryId;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Products | QARTIV</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css">

</head>


<body class="qartiv-products-page">


<!-- =========================================================
     HEADER
========================================================= -->

<header class="products-header">

    <div class="products-brand">

        <a href="dashboard.php">

            <div class="products-brand-mark">
                Q
            </div>

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
            <!-- USER -->
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
     MAIN
========================================================= -->

<main class="products-main">


<!-- =========================================================
     HERO
========================================================= -->

<section class="products-hero">

    <div class="products-hero-content">

        <span class="products-eyebrow">
            ✦ QARTIV COLLECTION
        </span>

        <h1>
            Discover something
            <span>you'll love.</span>
        </h1>

        <p>
            Explore our collection of products,
            find your favourites and shop smarter
            with Qartiv.
        </p>

    </div>


    <div class="products-hero-shape">
        Q
    </div>

</section>


<!-- =========================================================
     MESSAGE
========================================================= -->

<?php if ($message !== ""): ?>

    <div
        class="products-message <?php echo htmlspecialchars($messageType); ?>">

        <span>

            <?php
            echo $messageType === "success"
                ? "✓"
                : "!";
            ?>

        </span>

        <?php echo htmlspecialchars($message); ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     SEARCH + FILTER TOOLBAR
========================================================= -->

<section class="products-toolbar">

    <form method="GET" action="products.php" class="products-filter-bar">
        <div class="search-input-wrap">
            <span class="search-icon">⌕</span>
            <input
                type="text"
                name="search"
                value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Search products, brands...">
        </div>

        <?php if ($categoryId > 0): ?>
            <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
        <?php endif; ?>

        <!-- Price Range Filter -->
        <div class="filter-group">
            <span class="filter-label">Price ₹:</span>
            <input 
                type="number" 
                name="min_price" 
                value="<?php echo $minPrice > 0 ? htmlspecialchars($minPrice) : ''; ?>" 
                placeholder="Min" 
                min="0" 
                step="100" 
                class="filter-input-small">
            <span class="filter-sep">–</span>
            <input 
                type="number" 
                name="max_price" 
                value="<?php echo $maxPrice > 0 ? htmlspecialchars($maxPrice) : ''; ?>" 
                placeholder="Max" 
                min="0" 
                step="100" 
                class="filter-input-small">
        </div>

        <!-- Sort Filter -->
        <div class="filter-group">
            <span class="filter-label">Sort By:</span>
            <select name="sort" class="filter-select">
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Top Rated</option>
            </select>
        </div>

        <button type="submit" class="filter-submit-btn">Filter</button>

        <?php if ($search !== '' || $categoryId > 0 || $minPrice > 0 || $maxPrice > 0 || $sort !== 'newest'): ?>
            <a href="products.php" class="filter-reset-link" title="Clear all filters">Reset</a>
        <?php endif; ?>
    </form>


    <div class="products-category-filter">
        <?php
        $buildCatUrl = function($catId) use ($search, $minPrice, $maxPrice, $sort) {
            $p = [];
            if ($catId > 0) $p['category'] = $catId;
            if ($search !== '') $p['search'] = $search;
            if ($minPrice > 0) $p['min_price'] = $minPrice;
            if ($maxPrice > 0) $p['max_price'] = $maxPrice;
            if ($sort !== 'newest') $p['sort'] = $sort;
            return 'products.php' . (!empty($p) ? '?' . http_build_query($p) : '');
        };
        ?>

        <a
            href="<?php echo htmlspecialchars($buildCatUrl(0)); ?>"
            class="<?php echo $categoryId === 0 ? 'active' : ''; ?>">
            All Products
        </a>

        <?php foreach ($categories as $category): ?>
            <a
                href="<?php echo htmlspecialchars($buildCatUrl((int)$category["id"])); ?>"
                class="<?php echo $categoryId === (int)$category["id"] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($category["name"]); ?>
            </a>
        <?php endforeach; ?>
    </div>

</section>


<!-- =========================================================
     PRODUCT HEADER
========================================================= -->

<section class="products-heading-row">

    <div>

        <span>
            QARTIV STORE
        </span>

        <h2>

            <?php

            if ($search !== "") {

                echo "Search results";

            } elseif ($categoryId > 0) {

                foreach ($categories as $cat) {

                    if ((int) $cat["id"] === $categoryId) {

                        echo htmlspecialchars($cat["name"]);
                        break;
                    }
                }

            } else {

                echo "All products";

            }

            ?>

        </h2>

    </div>


    <p>

        <?php echo count($products); ?>

        product<?php echo count($products) !== 1 ? "s" : ""; ?>

    </p>

</section>


<!-- =========================================================
     PRODUCTS
========================================================= -->

<?php if (!empty($products)): ?>

<section class="products-grid">


<?php foreach ($products as $product): ?>

<?php

$productId = (int) $product["id"];

$price = (float) $product["price"];

$discount = (float) $product["discount"];

$stock = (int) $product["stock"];

$finalPrice = $price;

if ($discount > 0) {

    $finalPrice =
        $price - ($price * $discount / 100);

}

$isWishlist =
    in_array(
        $productId,
        $wishlistProducts,
        true
    );

?>


<article class="product-card">


<!-- =================================================
     IMAGE
================================================= -->

<div class="product-image-box">


    <?php if ($discount > 0): ?>

        <span class="product-discount">

            -<?php
            echo number_format(
                $discount,
                0
            );
            ?>%

        </span>

    <?php endif; ?>


    <?php if ($stock <= 0): ?>

        <span class="product-stock-badge sold-out">
            Out of stock
        </span>

    <?php elseif ($stock <= 5): ?>

        <span class="product-stock-badge low-stock">

            Only
            <?php echo $stock; ?>
            left

        </span>

    <?php endif; ?>


    <!-- WISHLIST -->

    <form
        method="POST"
        action="products.php<?php echo $currentQuery !== "" ? "?" . $currentQuery : ""; ?>"
        class="wishlist-form">

        <input
            type="hidden"
            name="product_id"
            value="<?php echo $productId; ?>">

        <button
            type="submit"
            name="toggle_wishlist"
            class="product-wishlist <?php echo $isWishlist ? 'liked' : ''; ?>"
            title="<?php echo $isWishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">

            <?php
            echo $isWishlist ? "♥" : "♡";
            ?>

        </button>

    </form>


    <!-- PRODUCT IMAGE LINK -->
    <a href="product_details.php?id=<?php echo $productId; ?>" class="product-image-link" title="<?php echo htmlspecialchars($product["name"]); ?>">
        <img
            src="<?php echo htmlspecialchars(getProductImageUrl($product["image"], "../")); ?>"
            alt="<?php echo htmlspecialchars($product["name"]); ?>"
            loading="lazy">
    </a>

</div>


<!-- =================================================
     DETAILS
================================================= -->

<div class="product-details">

    <div class="product-details-top">
        <span class="product-category">
            <?php
            echo htmlspecialchars(
                $product["category_name"]
            );
            ?>
        </span>

        <!-- Rating placeholder -->
        <div class="product-rating" title="<?php echo $product['avg_rating']; ?> out of 5 stars">
            <span class="rating-stars">★</span>
            <small><?php echo number_format($product['avg_rating'], 1); ?> (<?php echo $product['review_count']; ?>)</small>
        </div>
    </div>


    <h3>
        <a href="product_details.php?id=<?php echo $productId; ?>">
            <?php
            echo htmlspecialchars(
                $product["name"]
            );
            ?>
        </a>
    </h3>


    <p class="product-description">

        <?php

        $description =
            trim(
                $product["description"] ?? ""
            );

        if ($description === "") {

            echo "Premium quality product from Qartiv.";

        } elseif (strlen($description) > 75) {

            echo htmlspecialchars(
                substr(
                    $description,
                    0,
                    75
                )
            ) . "...";

        } else {

            echo htmlspecialchars(
                $description
            );

        }

        ?>

    </p>


    <!-- STOCK -->

    <?php if ($stock > 0): ?>

        <div class="product-stock-text">

            <span class="stock-dot"></span>

            <?php

            if ($stock <= 5) {

                echo "Only " . $stock . " left";

            } else {

                echo "In stock";
            }

            ?>

        </div>

    <?php else: ?>

        <div class="product-stock-text out">

            <span class="stock-dot"></span>

            Out of stock

        </div>

    <?php endif; ?>


    <!-- PRICE -->

    <div class="product-price-row">

        <div>

            <strong>

                ₹<?php
                echo number_format(
                    $finalPrice,
                    2
                );
                ?>

            </strong>


            <?php if ($discount > 0): ?>

                <del>

                    ₹<?php
                    echo number_format(
                        $price,
                        2
                    );
                    ?>

                </del>

            <?php endif; ?>

        </div>

    </div>


    <!-- =================================================
         CART
    ================================================== -->

    <div class="product-actions">

        <?php if ($stock > 0): ?>

            <form
                method="POST"
                action="products.php<?php echo $currentQuery !== "" ? "?" . $currentQuery : ""; ?>">

                <input
                    type="hidden"
                    name="product_id"
                    value="<?php echo $productId; ?>">

                <button
                    type="submit"
                    name="add_to_cart"
                    class="add-cart-btn">

                    <span>🛒</span>

                    Add to Cart

                </button>

            </form>

        <?php else: ?>

            <button
                type="button"
                class="add-cart-btn disabled"
                disabled>

                Out of Stock

            </button>

        <?php endif; ?>

    </div>


</div>

</article>


<?php endforeach; ?>

</section>


<?php else: ?>


<!-- =================================================
     EMPTY STATE
================================================= -->

<section class="products-empty">

    <div class="products-empty-icon">
        ◈
    </div>

    <h3>
        No products found
    </h3>

    <p>

        <?php if ($search !== ""): ?>

            We couldn't find anything matching
            "<?php echo htmlspecialchars($search); ?>".

        <?php else: ?>

            There are no products available
            in this collection right now.

        <?php endif; ?>

    </p>

    <a href="products.php">
        View All Products →
    </a>

</section>


<?php endif; ?>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="products-footer">

    <div>

        <strong>QARTIV</strong>

        <span>
            Smart E-Commerce Platform
        </span>

    </div>

    <p>

        © <?php echo date("Y"); ?> Qartiv.
        All rights reserved.

    </p>

</footer>


</body>
</html>