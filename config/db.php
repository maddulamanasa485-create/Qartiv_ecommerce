<?php
/**
 * QARTIV - Database Connection Configuration
 * Secure multi-environment database loader.
 * Compatible with Localhost (WAMP/XAMPP), Vercel (Cloud DB), and Live Hosting (InfinityFree, cPanel).
 */

// Default Local Database Settings (WAMP / Localhost)
$host = "localhost";
$user = "root";
$password = "";
$database = "qartiv";
$port = 3306;

// 1. Cloud & Container Environment Variables (Vercel, Render, Railway, Docker)
if (getenv('DB_HOST')) {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $database = getenv('DB_NAME') ?: 'qartiv';
    if (getenv('DB_PORT')) {
        $port = (int)getenv('DB_PORT');
    }
}

// 2. Private Local Credentials (Untracked in Git for hosting without env vars)
$privateConfigFile = __DIR__ . '/db.local.php';
if (file_exists($privateConfigFile)) {
    include $privateConfigFile;
}

$conn = @new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    die("<div style='font-family:Segoe UI,sans-serif;padding:30px;max-width:620px;margin:50px auto;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;color:#991b1b;box-shadow:0 10px 25px rgba(0,0,0,0.05);'>
        <h2 style='margin-top:0;font-size:20px;'>⚠️ Database Connection Error</h2>
        <p style='font-size:15px;line-height:1.5;'>Could not connect to the database: <code>" . htmlspecialchars($conn->connect_error) . "</code></p>
        <hr style='border:none;border-top:1px solid #fee2e2;margin:18px 0;'>
        <p style='color:#374151;font-size:13.5px;line-height:1.6;'>
            <strong>• On Localhost (WAMP):</strong> Ensure the WAMP MySQL service is running (green icon) and database <code>qartiv</code> is imported.<br>
            <strong>• On Live Server:</strong> Configure environment variables (<code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code>) or add credentials to <code>config/db.local.php</code>.
        </p>
    </div>");
}

$conn->set_charset("utf8mb4");

?>