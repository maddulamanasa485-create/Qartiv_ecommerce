<?php
session_start();
require_once "../config/db.php";
require_once "../config/helpers.php";

/* Access Check: Customer or Admin */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

$userRole = $_SESSION["user_role"] ?? "";
$userId = (int)$_SESSION["user_id"];
$orderId = (int)($_GET["id"] ?? 0);

if ($orderId <= 0) {
    die("Invalid Order ID.");
}

/* Fetch Order */
if ($userRole === "admin") {
    $stmt = $conn->prepare("
        SELECT o.*, u.name AS user_name, u.email AS user_email,
               a.full_name, a.phone, a.address, a.city, a.state, a.pincode
        FROM orders o
        INNER JOIN users u ON o.user_id = u.id
        LEFT JOIN addresses a ON o.address_id = a.id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $orderId);
} else {
    $stmt = $conn->prepare("
        SELECT o.*, u.name AS user_name, u.email AS user_email,
               a.full_name, a.phone, a.address, a.city, a.state, a.pincode
        FROM orders o
        INNER JOIN users u ON o.user_id = u.id
        LEFT JOIN addresses a ON o.address_id = a.id
        WHERE o.id = ? AND o.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $orderId, $userId);
}

$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order not found or unauthorized access.");
}

/* Fetch Items */
$itemStmt = $conn->prepare("
    SELECT oi.*, p.name AS product_name
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$itemStmt->bind_param("i", $orderId);
$itemStmt->execute();
$itemsRes = $itemStmt->get_result();
$items = [];
$subtotal = 0;
while ($row = $itemsRes->fetch_assoc()) {
    $items[] = $row;
    $subtotal += ((float)$row['price'] * (int)$row['quantity']);
}
$itemStmt->close();

$shipping = $subtotal < 999 && $subtotal > 0 ? 79 : 0;
$discountAmount = max(0, ($subtotal + $shipping) - (float)$order['total_amount']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice #QTV-<?php echo str_pad($orderId, 6, "0", STR_PAD_LEFT); ?> | QARTIV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            background: #f4f3ef;
            color: #1a1e36;
            margin: 0;
            padding: 30px 15px;
        }

        .invoice-wrapper {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 48px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e5e2da;
        }

        .no-print-bar {
            max-width: 820px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .print-btn {
            background: #1a1e36;
            color: #ffffff;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .print-btn:hover {
            background: #d4af37;
            color: #1a1e36;
        }

        .back-link {
            color: #6b7280;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1a1e36;
            padding-bottom: 24px;
            margin-bottom: 28px;
        }

        .invoice-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-badge {
            width: 44px;
            height: 44px;
            background: #1a1e36;
            color: #d4af37;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 900;
        }

        .brand-title h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #1a1e36;
        }

        .brand-title p {
            margin: 3px 0 0;
            font-size: 11px;
            color: #8c90a4;
            letter-spacing: 1.5px;
            font-weight: 700;
        }

        .invoice-meta {
            text-align: right;
        }

        .invoice-meta h2 {
            margin: 0 0 4px;
            font-size: 20px;
            color: #1a1e36;
            font-weight: 850;
        }

        .meta-row {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.6;
        }

        .invoice-parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-bottom: 32px;
            font-size: 13px;
            line-height: 1.6;
        }

        .party-box h3 {
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #d4af37;
            font-weight: 800;
            margin: 0 0 8px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .invoice-table th {
            background: #faf8f5;
            padding: 12px 14px;
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #555a6d;
            border-bottom: 2px solid #ede8de;
            text-align: left;
        }

        .invoice-table td {
            padding: 14px;
            font-size: 13px;
            border-bottom: 1px solid #f1ece4;
            color: #2e344d;
        }

        .invoice-table th.text-right,
        .invoice-table td.text-right {
            text-align: right;
        }

        .invoice-totals {
            margin-left: auto;
            width: 320px;
            margin-bottom: 30px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
            color: #555a6d;
        }

        .total-row.grand {
            border-top: 2px solid #1a1e36;
            margin-top: 10px;
            padding-top: 12px;
            font-size: 16px;
            font-weight: 850;
            color: #1a1e36;
        }

        .invoice-footer {
            border-top: 1px solid #ede8de;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #8c90a4;
        }

        @media print {
            body { background: #ffffff; padding: 0; }
            .invoice-wrapper { box-shadow: none; border: none; padding: 20px; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print-bar no-print">
    <a href="orders.php" class="back-link">← Back to Orders</a>
    <button onclick="window.print()" class="print-btn">🖨️ Print / Save as PDF</button>
</div>

<div class="invoice-wrapper">

    <!-- Header -->
    <div class="invoice-header">
        <div class="invoice-brand">
            <div class="brand-badge">Q</div>
            <div class="brand-title">
                <h1>QARTIV</h1>
                <p>SMART COMMERCE PLATFORM</p>
            </div>
        </div>

        <div class="invoice-meta">
            <h2>TAX INVOICE</h2>
            <div class="meta-row"><strong>Invoice #:</strong> QTV-<?php echo str_pad($orderId, 6, "0", STR_PAD_LEFT); ?></div>
            <div class="meta-row"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
            <div class="meta-row"><strong>Payment:</strong> <?php echo htmlspecialchars($order['payment_method']); ?> (<?php echo strtoupper($order['payment_status']); ?>)</div>
        </div>
    </div>

    <!-- Parties -->
    <div class="invoice-parties">
        <div class="party-box">
            <h3>Sold By</h3>
            <strong>Qartiv Commerce Retail Pvt. Ltd.</strong><br>
            Tech Park Avenue, 4th Floor<br>
            Cyber City, Hyderabad, India - 500081<br>
            GSTIN: 36AAACQ9876Q1Z0<br>
            Email: support@qartiv.com
        </div>

        <div class="party-box">
            <h3>Billed & Shipped To</h3>
            <strong><?php echo htmlspecialchars($order['full_name']); ?></strong><br>
            <?php echo nl2br(htmlspecialchars($order['address'])); ?><br>
            <?php echo htmlspecialchars($order['city']); ?>, <?php echo htmlspecialchars($order['state']); ?> - <?php echo htmlspecialchars($order['pincode']); ?><br>
            Phone: <?php echo htmlspecialchars($order['phone']); ?><br>
            Email: <?php echo htmlspecialchars($order['user_email']); ?>
        </div>
    </div>

    <!-- Items Table -->
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width: 45%;">Item Description</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($it['product_name']); ?></strong><br>
                        <small style="color:#8c90a4;">Product ID: #<?php echo (int)$it['product_id']; ?></small>
                    </td>
                    <td class="text-right">₹<?php echo number_format($it['price'], 2); ?></td>
                    <td class="text-right"><?php echo (int)$it['quantity']; ?></td>
                    <td class="text-right">₹<?php echo number_format($it['price'] * $it['quantity'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="invoice-totals">
        <div class="total-row">
            <span>Subtotal</span>
            <span>₹<?php echo number_format($subtotal, 2); ?></span>
        </div>

        <?php if ($discountAmount > 0): ?>
            <div class="total-row" style="color: #059669;">
                <span>Coupon / Promotion</span>
                <span>-₹<?php echo number_format($discountAmount, 2); ?></span>
            </div>
        <?php endif; ?>

        <div class="total-row">
            <span>Shipping & Handling</span>
            <span><?php echo $shipping == 0 ? "FREE" : "₹" . number_format($shipping, 2); ?></span>
        </div>

        <div class="total-row grand">
            <span>Grand Total</span>
            <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
        </div>
    </div>

    <!-- Footer -->
    <div class="invoice-footer">
        <div>
            Thank you for shopping with <strong>QARTIV</strong>.<br>
            This is a computer-generated tax invoice. No signature is required.
        </div>
        <div>
            Questions? Contact <strong>concierge@qartiv.com</strong>
        </div>
    </div>

</div>

</body>
</html>
