<?php
session_start();
require_once "../config/db.php";
require_once "../config/helpers.php";

/* Customer Authentication */
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];
$userName = $_SESSION["user_name"] ?? "Customer";
$orderId = (int)($_GET["id"] ?? 0);

if ($orderId <= 0) {
    header("Location: orders.php");
    exit;
}

/* Fetch Order */
$stmt = $conn->prepare("
    SELECT o.*, a.full_name, a.phone, a.address, a.city, a.state, a.pincode
    FROM orders o
    LEFT JOIN addresses a ON o.address_id = a.id
    WHERE o.id = ? AND o.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: orders.php");
    exit;
}

/* Fetch Order Items */
$itemStmt = $conn->prepare("
    SELECT oi.*, p.name, p.image, p.price AS original_price, p.discount
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$itemStmt->bind_param("i", $orderId);
$itemStmt->execute();
$itemsResult = $itemStmt->get_result();
$items = [];
$calculatedSubtotal = 0;
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
    $calculatedSubtotal += ((float)$row['price'] * (int)$row['quantity']);
}
$itemStmt->close();

$estDeliveryDate = date('D, M j, Y', strtotime($order['created_at'] . ' + 4 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed #<?php echo $orderId; ?> | QARTIV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', Inter, sans-serif;
            background: #faf9f6;
            color: #1a1e36;
            margin: 0;
            padding: 0;
        }

        .success-page-container {
            max-width: 860px;
            margin: 40px auto 80px;
            padding: 0 20px;
        }

        .success-hero-card {
            background: #ffffff;
            border: 1px solid #ede9df;
            border-radius: 20px;
            padding: 48px 36px;
            text-align: center;
            box-shadow: 0 14px 40px rgba(35, 30, 20, 0.05);
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }

        .success-hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #d4af37, #c5a880, #1a1e36);
        }

        .success-icon-badge {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: #ecfdf5;
            color: #059669;
            font-size: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            border: 2px solid #a7f3d0;
        }

        .success-title {
            font-size: 28px;
            font-weight: 850;
            color: #1a1e36;
            margin: 0 0 10px;
            letter-spacing: -0.5px;
        }

        .success-subtitle {
            font-size: 15px;
            color: #6b7280;
            margin: 0 0 24px;
        }

        .order-reference-pill {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: #fdfbf7;
            border: 1px solid #e8dfcf;
            padding: 10px 22px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            color: #855b14;
        }

        .success-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
        }

        .details-card {
            background: #ffffff;
            border: 1px solid #ede9df;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.02);
        }

        .details-card h3 {
            margin: 0 0 20px;
            font-size: 17px;
            font-weight: 800;
            color: #1a1e36;
            border-bottom: 1px solid #f1ece4;
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .success-item-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px dashed #f1ece4;
        }

        .success-item-row:last-child {
            border-bottom: none;
        }

        .success-item-img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            border: 1px solid #ede9df;
            object-fit: cover;
            background: #faf8f5;
        }

        .success-item-info {
            flex: 1;
        }

        .success-item-name {
            font-weight: 700;
            font-size: 14px;
            color: #1a1e36;
            margin-bottom: 4px;
        }

        .success-item-meta {
            font-size: 12px;
            color: #8c90a4;
        }

        .success-item-price {
            font-weight: 800;
            font-size: 14px;
            color: #1a1e36;
        }

        .summary-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            color: #6b7280;
        }

        .summary-line strong {
            color: #1a1e36;
        }

        .summary-line.total-line {
            border-top: 1px solid #ede9df;
            margin-top: 12px;
            padding-top: 14px;
            font-size: 17px;
            font-weight: 850;
            color: #1a1e36;
        }

        .action-buttons-row {
            margin-top: 32px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-action-primary {
            background: #1a1e36;
            color: #ffffff;
            padding: 14px 26px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 14px;
            text-decoration: none;
            transition: 0.2s ease;
            box-shadow: 0 6px 18px rgba(26,30,54,0.18);
        }

        .btn-action-primary:hover {
            background: #d4af37;
            color: #1a1e36;
        }

        .btn-action-outline {
            background: #ffffff;
            color: #1a1e36;
            border: 1px solid #d4af37;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .btn-action-outline:hover {
            background: #fdfbf7;
            border-color: #1a1e36;
        }

        @media (max-width: 768px) {
            .success-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<header class="products-header">
    <div class="products-brand">
        <a href="dashboard.php" style="text-decoration:none;display:flex;align-items:center;gap:12px;">
            <div class="products-brand-mark" style="background:#1a1e36;color:#d4af37;width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:10px;font-weight:900;">Q</div>
            <div class="products-brand-text">
                <strong style="color:#1a1e36;font-size:16px;display:block;">QARTIV</strong>
                <span style="color:#8c90a4;font-size:10px;letter-spacing:1px;">SMART COMMERCE</span>
            </div>
        </a>
    </div>
    <nav class="products-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="products.php">Shop Catalog</a>
        <a href="orders.php" class="active">My Orders</a>
    </nav>
</header>

<div class="success-page-container">

    <div class="success-hero-card">
        <div class="success-icon-badge">✓</div>
        <h1 class="success-title">Thank You, <?php echo htmlspecialchars($userName); ?>!</h1>
        <p class="success-subtitle">Your order has been placed and is currently being processed by our fulfillment center.</p>

        <div class="order-reference-pill">
            <span>Order #QTV-<?php echo str_pad($orderId, 6, "0", STR_PAD_LEFT); ?></span>
            <span>•</span>
            <span>Estimated Delivery: <?php echo $estDeliveryDate; ?></span>
        </div>
    </div>

    <div class="success-grid">

        <!-- Ordered Items -->
        <div class="details-card">
            <h3>Items in this Order (<?php echo count($items); ?>)</h3>
            <?php foreach ($items as $it): ?>
                <div class="success-item-row">
                    <img 
                        src="<?php echo htmlspecialchars(getProductImageUrl($it['image'], '../')); ?>" 
                        alt="<?php echo htmlspecialchars($it['name']); ?>" 
                        class="success-item-img">
                    <div class="success-item-info">
                        <div class="success-item-name"><?php echo htmlspecialchars($it['name']); ?></div>
                        <div class="success-item-meta">Qty: <?php echo (int)$it['quantity']; ?> × ₹<?php echo number_format($it['price'], 2); ?></div>
                    </div>
                    <div class="success-item-price">
                        ₹<?php echo number_format($it['price'] * $it['quantity'], 2); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Delivery & Payment Summary -->
        <div class="details-card">
            <h3>Delivery Details</h3>
            <p style="font-size:13px;line-height:1.6;color:#555a6d;margin:0 0 16px;">
                <strong style="color:#1a1e36;"><?php echo htmlspecialchars($order['full_name']); ?></strong><br>
                <?php echo nl2br(htmlspecialchars($order['address'])); ?><br>
                <?php echo htmlspecialchars($order['city']); ?>, <?php echo htmlspecialchars($order['state']); ?> - <?php echo htmlspecialchars($order['pincode']); ?><br>
                📞 <?php echo htmlspecialchars($order['phone']); ?>
            </p>

            <h3 style="margin-top:20px;">Payment Breakdown</h3>
            <div class="summary-line">
                <span>Payment Method</span>
                <strong>
                    <?php 
                        $pm = strtoupper($order['payment_method'] ?? 'COD');
                        if ($pm === 'UPI') {
                            echo '⚡ Instant UPI (Prepaid)';
                        } elseif ($pm === 'CARD') {
                            echo '💳 Credit/Debit Card (Prepaid)';
                        } else {
                            echo '💵 Cash on Delivery (Pay upon delivery)';
                        }
                    ?>
                </strong>
            </div>
            <div class="summary-line">
                <span>Payment Status</span>
                <?php $isPaid = strtolower($order['payment_status'] ?? '') === 'paid'; ?>
                <strong style="color: <?php echo $isPaid ? '#047857' : '#b45309'; ?>; background: <?php echo $isPaid ? '#ecfdf5' : '#fffbeb'; ?>; padding: 2px 8px; border-radius: 4px;">
                    <?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?>
                </strong>
            </div>
            <div class="summary-line">
                <span>Order Status</span>
                <strong style="color:#047857;"><?php echo ucfirst(htmlspecialchars($order['order_status'])); ?></strong>
            </div>
            <div class="summary-line total-line">
                <span>Amount Paid / Due</span>
                <span style="color:#1a1e36;">₹<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>

    </div>

    <!-- Action Buttons -->
    <div class="action-buttons-row">
        <a href="order_details.php?id=<?php echo $orderId; ?>" class="btn-action-primary">View Full Order Details</a>
        <a href="invoice.php?id=<?php echo $orderId; ?>" target="_blank" class="btn-action-outline">📄 Download Printable Invoice</a>
        <a href="products.php" class="btn-action-outline">Continue Shopping</a>
    </div>

</div>

</body>
</html>
