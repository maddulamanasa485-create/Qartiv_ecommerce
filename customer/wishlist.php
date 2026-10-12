<?php
session_start();
require_once "../config/db.php";
require_once "../config/helpers.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

/* WISHLIST ACTIONS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_id = (int)($_POST["product_id"] ?? 0);
    $action = $_POST["action"] ?? "remove";

    if ($product_id > 0) {
        if ($action === "move_to_cart") {
            $sStmt = $conn->prepare("SELECT stock, name FROM products WHERE id = ? AND status = 'active' LIMIT 1");
            $sStmt->bind_param("i", $product_id);
            $sStmt->execute();
            $pData = $sStmt->get_result()->fetch_assoc();
            $sStmt->close();

            if ($pData && (int)$pData['stock'] > 0) {
                $cStmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? LIMIT 1");
                $cStmt->bind_param("ii", $user_id, $product_id);
                $cStmt->execute();
                $cItem = $cStmt->get_result()->fetch_assoc();
                $cStmt->close();

                if ($cItem) {
                    $uStmt = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = ?");
                    $uStmt->bind_param("i", $cItem['id']);
                    $uStmt->execute();
                    $uStmt->close();
                } else {
                    $iStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
                    $iStmt->bind_param("ii", $user_id, $product_id);
                    $iStmt->execute();
                    $iStmt->close();
                }

                $delStmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                $delStmt->bind_param("ii", $user_id, $product_id);
                $delStmt->execute();
                $delStmt->close();

                $_SESSION['wishlist_msg'] = htmlspecialchars($pData['name']) . " moved to your cart!";
                $_SESSION['wishlist_msg_type'] = "success";
            } else {
                $_SESSION['wishlist_msg'] = "This item is currently out of stock.";
                $_SESSION['wishlist_msg_type'] = "error";
            }
        } else {
            $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['wishlist_msg'] = "Item removed from wishlist.";
            $_SESSION['wishlist_msg_type'] = "info";
        }
    }

    header("Location: wishlist.php");
    exit;
}

$wishlistMsg = $_SESSION['wishlist_msg'] ?? '';
$wishlistMsgType = $_SESSION['wishlist_msg_type'] ?? '';
unset($_SESSION['wishlist_msg'], $_SESSION['wishlist_msg_type']);


/* GET WISHLIST */
$stmt = $conn->prepare("
    SELECT
        products.id,
        products.name,
        products.description,
        products.price,
        products.discount,
        products.stock,
        products.image,
        categories.name AS category_name
    FROM wishlist
    INNER JOIN products
        ON wishlist.product_id = products.id
    LEFT JOIN categories
        ON products.category_id = categories.id
    WHERE wishlist.user_id = ?
      AND products.status = 'active'
    ORDER BY wishlist.created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$products = [];

while ($row = $result->fetch_assoc()) {

    $row["sale_price"] =
        $row["price"] - (($row["price"] * $row["discount"]) / 100);

    $products[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Wishlist | QARTIV</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

body {
    margin: 0;
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f5f7fc;
    color: #18213d;
}

.wishlist-page {
    min-height: 100vh;
}

.wishlist-header {
    height: 82px;
    background: white;
    border-bottom: 1px solid #e7eaf2;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 55px;
}

.wishlist-logo {
    font-size: 25px;
    font-weight: 900;
    letter-spacing: 3px;
    color: #172354;
}

.wishlist-logo span {
    color: #6870ff;
}

.wishlist-header a {
    color: #6870ff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.wishlist-container {
    max-width: 1200px;
    margin: auto;
    padding: 42px 25px;
}

.wishlist-title h1 {
    margin: 0;
    font-size: 30px;
}

.wishlist-title p {
    color: #8c93a7;
    font-size: 13px;
    margin-top: 7px;
}

.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-top: 30px;
}

.wishlist-card {
    background: white;
    border: 1px solid #e7eaf2;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 7px 25px rgba(25,35,75,.05);
    transition: .25s ease;
}

.wishlist-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(25,35,75,.10);
}

.wishlist-image {
    height: 190px;
    background: linear-gradient(135deg,#eef0ff,#f8f8ff);
    display: flex;
    align-items: center;
    justify-content: center;
}

.wishlist-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.wishlist-placeholder {
    font-size: 45px;
    font-weight: 900;
    color: #6870ff;
}

.wishlist-info {
    padding: 18px;
}

.wishlist-category {
    color: #6870ff;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.wishlist-name {
    font-size: 14px;
    font-weight: 800;
    margin-top: 7px;
}

.wishlist-description {
    font-size: 10px;
    color: #9198ab;
    margin-top: 6px;
    min-height: 30px;
}

.wishlist-price {
    margin-top: 13px;
}

.wishlist-sale {
    font-size: 15px;
    font-weight: 900;
}

.wishlist-original {
    color: #a2a7b6;
    text-decoration: line-through;
    font-size: 10px;
    margin-left: 5px;
}

.wishlist-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}

.wishlist-shop {
    flex: 1;
    background: #5969ff;
    color: white;
    text-align: center;
    padding: 10px;
    border-radius: 9px;
    text-decoration: none;
    font-size: 10px;
    font-weight: 800;
}

.wishlist-remove {
    border: 1px solid #f0dce0;
    background: #fff7f8;
    color: #df6c7c;
    padding: 10px 12px;
    border-radius: 9px;
    cursor: pointer;
}

.empty-wishlist {
    background: white;
    border: 1px solid #e8ebf3;
    border-radius: 20px;
    padding: 80px 20px;
    text-align: center;
    margin-top: 30px;
}

.empty-wishlist-icon {
    font-size: 45px;
    color: #6870ff;
}

.empty-wishlist h2 {
    margin-bottom: 7px;
}

.empty-wishlist p {
    color: #9299aa;
    font-size: 13px;
}

.shop-button {
    display: inline-block;
    margin-top: 15px;
    background: #5969ff;
    color: white;
    padding: 12px 22px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

@media(max-width:950px) {
    .wishlist-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width:550px) {
    .wishlist-grid {
        grid-template-columns: 1fr;
    }

    .wishlist-header {
        padding: 0 20px;
    }
}

</style>

</head>

<body>

<div class="wishlist-page">

<header class="wishlist-header">

    <div class="wishlist-logo">
        QART<span>IV</span>
    </div>

    <a href="products.php">
        ← Continue Shopping
    </a>

</header>

<main class="wishlist-container">

<div class="wishlist-title">

    <h1>My Wishlist ♡</h1>

    <p>
        <?php echo count($products); ?>
        saved product<?php echo count($products) != 1 ? "s" : ""; ?>
    </p>

</div>


<?php if (count($products) === 0): ?>

<div class="empty-wishlist">

    <div class="empty-wishlist-icon">
        ♡
    </div>

    <h2>Your wishlist is empty</h2>

    <p>
        Save products you love and find them here later.
    </p>

    <a href="products.php" class="shop-button">
        Explore Products →
    </a>

</div>

<?php else: ?>

<?php if (!empty($wishlistMsg)): ?>
    <div style="padding:13px 18px;border-radius:12px;margin-bottom:20px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:10px;background:<?php echo $wishlistMsgType === 'success' ? '#ecfdf5' : '#f0f4ff'; ?>;color:<?php echo $wishlistMsgType === 'success' ? '#065f46' : '#374151'; ?>;border:1px solid <?php echo $wishlistMsgType === 'success' ? '#a7f3d0' : '#e5e7eb'; ?>;">
        <span><?php echo $wishlistMsgType === 'success' ? '✓' : 'ℹ'; ?></span>
        <?php echo htmlspecialchars($wishlistMsg); ?>
    </div>
<?php endif; ?>

<div class="wishlist-grid">

<?php foreach ($products as $product): ?>

<div class="wishlist-card">

    <div class="wishlist-image">
        <a href="product_details.php?id=<?php echo (int)$product["id"]; ?>" style="display: block; width: 100%; height: 100%;">
            <img
                src="<?php echo htmlspecialchars(getProductImageUrl($product["image"], "../")); ?>"
                alt="<?php echo htmlspecialchars($product["name"]); ?>"
                loading="lazy"
            >
        </a>
    </div>


    <div class="wishlist-info">

        <div class="wishlist-category">
            <?php echo htmlspecialchars($product["category_name"]); ?>
        </div>

        <div class="wishlist-name">
            <?php echo htmlspecialchars($product["name"]); ?>
        </div>

        <div class="wishlist-description">
            <?php
            echo htmlspecialchars(
                mb_substr($product["description"] ?? "", 0, 65)
            );
            ?>
        </div>

        <div class="wishlist-price">

            <span class="wishlist-sale">
                ₹<?php echo number_format($product["sale_price"], 2); ?>
            </span>

            <?php if ($product["discount"] > 0): ?>

                <span class="wishlist-original">
                    ₹<?php echo number_format($product["price"], 2); ?>
                </span>

            <?php endif; ?>

        </div>


        <div class="wishlist-actions">

            <form method="POST" style="margin:0;">
                <input
                    type="hidden"
                    name="product_id"
                    value="<?php echo $product["id"]; ?>"
                >
                <input type="hidden" name="action" value="move_to_cart">
                <button type="submit" class="wishlist-shop" style="cursor:pointer;border:none;">
                    🛒 Move to Cart
                </button>
            </form>

            <a href="product_details.php?id=<?php echo (int)$product["id"]; ?>" class="wishlist-shop" style="background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;">
                Details
            </a>

            <form method="POST" style="margin:0;">

                <input
                    type="hidden"
                    name="product_id"
                    value="<?php echo $product["id"]; ?>"
                >
                <input type="hidden" name="action" value="remove">

                <button
                    type="submit"
                    class="wishlist-remove"
                    title="Remove"
                >
                    ✕
                </button>

            </form>

        </div>

    </div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</main>

</div>

</body>
</html>