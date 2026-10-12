<?php
/**
 * QARTIV - System Audit & Live Link Readiness Dashboard
 * Checks Database, Product Images, Uploads, and provides Live Sharing links.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

// Detect current host and protocol
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $isHttps ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentUrl = $protocol . $host . $_SERVER['REQUEST_URI'];
$baseUrl = $protocol . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Audit Database Tables
$tables = [
    'users' => 0,
    'products' => 0,
    'categories' => 0,
    'orders' => 0,
    'order_items' => 0,
    'cart' => 0,
    'wishlist' => 0,
    'reviews' => 0,
    'coupons' => 0,
    'addresses' => 0
];

$tableErrors = [];
foreach (array_keys($tables) as $tableName) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM `$tableName`");
    if ($res) {
        $row = $res->fetch_assoc();
        $tables[$tableName] = (int)$row['total'];
    } else {
        $tableErrors[$tableName] = $conn->error;
    }
}

// Audit Products & Images
$productsQuery = $conn->query("SELECT id, name, price, discount, image, status FROM products ORDER BY id ASC");
$products = [];
if ($productsQuery) {
    while ($row = $productsQuery->fetch_assoc()) {
        $imgPath = __DIR__ . '/uploads/products/' . $row['image'];
        $row['image_exists'] = !empty($row['image']) && file_exists($imgPath);
        $row['resolved_url'] = getProductImageUrl($row['image'], '');
        $products[] = $row;
    }
}

// Category images audit
$categoriesQuery = $conn->query("SELECT id, name, image FROM categories ORDER BY id ASC");
$categories = [];
if ($categoriesQuery) {
    while ($row = $categoriesQuery->fetch_assoc()) {
        $imgPath = __DIR__ . '/uploads/products/' . $row['image'];
        $row['image_exists'] = !empty($row['image']) && file_exists($imgPath);
        $row['resolved_url'] = getProductImageUrl($row['image'], '');
        $categories[] = $row;
    }
}

// Check Uploads Permissions
$uploadsDir = __DIR__ . '/uploads/products/';
$uploadsWritable = is_dir($uploadsDir) && is_writable($uploadsDir);
$totalImagesInDir = is_dir($uploadsDir) ? count(glob($uploadsDir . '*.*')) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qartiv System Audit & Live Link Readiness</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090d16;
            --card: rgba(18, 24, 38, 0.85);
            --card-border: rgba(255, 255, 255, 0.08);
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --emerald: #10b981;
            --emerald-bg: rgba(16, 185, 129, 0.12);
            --rose: #f43f5e;
            --rose-bg: rgba(244, 63, 94, 0.12);
            --amber: #f59e0b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.12) 0%, transparent 40%);
            color: var(--text-main);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 1160px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 36px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 9999px;
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 14px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        h1 {
            font-size: 2.4rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 1.05rem;
            max-width: 650px;
            margin: 0 auto 24px;
        }

        .nav-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.07);
            color: var(--text-main);
            border: 1px solid var(--card-border);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            backdrop-filter: blur(12px);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 12px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
        }
        .stat-val {
            font-size: 1.6rem;
            font-weight: 800;
            color: #a5b4fc;
        }
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: capitalize;
            margin-top: 4px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .badge-success {
            background: var(--emerald-bg);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .badge-danger {
            background: var(--rose-bg);
            color: #fb7185;
            border: 1px solid rgba(244, 63, 94, 0.25);
        }

        .prod-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: #1e293b;
        }

        .code-box {
            background: #050810;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.88rem;
            color: #38bdf8;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 10px 0 16px;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #f8fafc;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: 0.2s;
        }
        .copy-btn:hover {
            background: var(--primary);
        }

        .highlight-banner {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(16, 185, 129, 0.15) 100%);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .live-url-display {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.1rem;
            color: #a5f3fc;
            word-break: break-all;
            background: rgba(0, 0, 0, 0.4);
            padding: 12px 18px;
            border-radius: 8px;
            margin: 12px 0;
            border: 1px dashed rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Header -->
    <div class="header">
        <div class="brand-badge">⚡ QARTIV SYSTEM DIAGNOSTIC & SHARING PORTAL</div>
        <h1>Live Readiness & Product Image Audit</h1>
        <p class="subtitle">Confirm database connectivity, verify all 8 product images and banners, and generate your live public link so anyone can view Qartiv anywhere.</p>

        <div class="nav-actions">
            <a href="index.php" class="btn btn-primary">🛍️ View Storefront</a>
            <a href="customer/products.php" class="btn btn-secondary">🔍 Shop Products</a>
            <a href="customer/cart.php" class="btn btn-secondary">🛒 View Cart</a>
            <a href="admin/dashboard.php" class="btn btn-secondary">🛡️ Admin Dashboard</a>
        </div>
    </div>

    <!-- Live Link Generation Guide -->
    <div class="highlight-banner">
        <div class="card-title">
            <span>🌐 How to get your LIVE LINK for Anyone, Anywhere</span>
        </div>
        <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 12px;">
            Choose either <strong>Instant Live Link (Option 1)</strong> which gives you a public link in 5 seconds right now, or <strong>Free Cloud Hosting (Option 2)</strong> for 24/7 permanent hosting.
        </p>

        <h3 style="margin-top: 18px; font-size: 1.05rem; color: #fbbf24;">🚀 Option 1: Instant Public Live Tunnel (Works Right Now with WAMP)</h3>
        <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 4px;">
            Open <strong>PowerShell</strong> or <strong>Command Prompt</strong> on your computer and run this command:
        </p>
        <div class="code-box">
            <span id="cmdPinggy">ssh -p 443 -R0:localhost:80 a.pinggy.io</span>
            <button class="copy-btn" onclick="copyText('cmdPinggy')">📋 Copy</button>
        </div>
        <p style="font-size: 0.85rem; color: #a5b4fc;">
            ✨ Pinggy connects via Windows OpenSSH (no downloads required) and prints an instant public <code>https://xxxxx.a.pinggy.link/Qartiv/</code> link that opens on any phone, tablet, or PC worldwide!
        </p>

        <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.08);">
            <p style="font-size: 0.9rem; color: var(--text-muted);">Alternative with <strong>Cloudflare Tunnel</strong> (free, fast, unlimited):</p>
            <div class="code-box">
                <span id="cmdCloudflare">winget install --id Cloudflare.cloudflared -e; cloudflared tunnel --url http://localhost:80</span>
                <button class="copy-btn" onclick="copyText('cmdCloudflare')">📋 Copy</button>
            </div>
        </div>

        <h3 style="margin-top: 24px; font-size: 1.05rem; color: #34d399;">☁️ Option 2: Permanent 24/7 Cloud Hosting (InfinityFree)</h3>
        <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; line-height: 1.6;">
            1. Sign up free at <a href="https://www.infinityfree.com" target="_blank" style="color: #60a5fa;">InfinityFree.com</a>.<br>
            2. In Control Panel &rarr; File Manager, upload and extract <code>Qartiv_Final_Project.zip</code> (already ready in your project root).<br>
            3. In phpMyAdmin on InfinityFree, import <code>qartiv_database.sql</code>.<br>
            4. In <code>config/db.php</code>, enter your InfinityFree MySQL hostname, username, and password.
        </p>
    </div>

    <!-- Active System Summary -->
    <div class="grid-2">
        <!-- DB Summary -->
        <div class="card">
            <div class="card-title">
                <span>🗄️ Database Status</span>
                <span class="badge badge-success">Connected</span>
            </div>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 16px;">
                Host: <code><?= htmlspecialchars($host); ?></code> | Database: <code>qartiv</code>
            </p>

            <div class="stat-grid">
                <?php foreach ($tables as $name => $count): ?>
                    <div class="stat-card">
                        <div class="stat-val"><?= $count ?></div>
                        <div class="stat-label"><?= htmlspecialchars($name) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Files & Uploads Summary -->
        <div class="card">
            <div class="card-title">
                <span>📁 Uploads & Media Assets</span>
                <?php if ($uploadsWritable): ?>
                    <span class="badge badge-success">Folder Writable</span>
                <?php else: ?>
                    <span class="badge badge-danger">Check Permissions</span>
                <?php endif; ?>
            </div>
            <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.6; margin-bottom: 16px;">
                Path: <code>uploads/products/</code><br>
                Total files in folder: <strong><?= $totalImagesInDir ?> files</strong><br>
                Current Base URL: <code><?= htmlspecialchars($baseUrl) ?></code>
            </p>

            <h4 style="font-size: 0.9rem; margin-bottom: 10px; color: #cbd5e1;">Category Banners:</h4>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php foreach ($categories as $cat): ?>
                    <div style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.03); padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                        <img src="<?= htmlspecialchars($cat['resolved_url']) ?>" style="width: 32px; height: 32px; object-fit: cover; border-radius: 6px;">
                        <span style="font-size: 0.85rem;"><?= htmlspecialchars($cat['name']) ?></span>
                        <?php if ($cat['image_exists']): ?>
                            <span class="badge badge-success" style="padding: 2px 6px; font-size: 0.7rem;">✓</span>
                        <?php else: ?>
                            <span class="badge badge-danger" style="padding: 2px 6px; font-size: 0.7rem;">✗</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Product Image Audit Table -->
    <div class="card">
        <div class="card-title">
            <span>📸 Product Catalog & Image Verification</span>
            <span class="badge badge-success"><?= count($products) ?> Products Loaded</span>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Preview</th>
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Image File</th>
                        <th>File on Disk</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>#<?= $p['id'] ?></td>
                            <td>
                                <img src="<?= htmlspecialchars($p['resolved_url']) ?>" 
                                     alt="<?= htmlspecialchars($p['name']) ?>" 
                                     class="prod-thumb"
                                     onerror="this.src='assets/images/default-product.svg'">
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($p['name']) ?></strong>
                            </td>
                            <td>₹<?= number_format($p['price'], 2) ?></td>
                            <td><code><?= htmlspecialchars($p['image']) ?></code></td>
                            <td>
                                <?php if ($p['image_exists']): ?>
                                    <span class="badge badge-success">✓ Verified on Disk</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">✗ File Missing</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-success"><?= htmlspecialchars($p['status']) ?></span>
                            </td>
                            <td>
                                <a href="customer/product_details.php?id=<?= $p['id'] ?>" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.8rem;" target="_blank">
                                    View ↗
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function copyText(id) {
    const text = document.getElementById(id).innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert("Command copied to clipboard! Paste it into PowerShell or Command Prompt to launch your live link.");
    }).catch(err => {
        prompt("Copy this command:", text);
    });
}
</script>

</body>
</html>
