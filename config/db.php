<?php
/**
 * QARTIV - Database Connection Configuration
 * Secure multi-environment database loader.
 * Compatible with Localhost (WAMP/XAMPP), Vercel (Cloud DB like TiDB/Aiven), and Live Hosting.
 */

// Default Local Database Settings (WAMP / Localhost)
$host = "localhost";
$user = "root";
$password = "";
$database = "qartiv";
$port = 3306;

// 1. Cloud & Serverless Environment Variables (Vercel, Render, Railway, Docker)
if (getenv('DB_HOST')) {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
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

// Initialize MySQLi
$conn = mysqli_init();

// Enable SSL if connecting to cloud providers (TiDB Cloud, Aiven, or DB_SSL=true)
$isCloudSsl = (getenv('DB_SSL') === 'true') || ($port === 4000) || (strpos($host, 'tidbcloud.com') !== false);

if ($isCloudSsl) {
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    @$conn->real_connect($host, $user, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL);
} else {
    @$conn->real_connect($host, $user, $password, $database, $port);
}

if ($conn->connect_error) {
    die("<div style='font-family:Segoe UI,sans-serif;padding:30px;max-width:620px;margin:50px auto;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;color:#991b1b;box-shadow:0 10px 25px rgba(0,0,0,0.05);'>
        <h2 style='margin-top:0;font-size:20px;'>⚠️ Database Connection Error</h2>
        <p style='font-size:15px;line-height:1.5;'>Could not connect to the database: <code>" . htmlspecialchars($conn->connect_error) . "</code></p>
        <hr style='border:none;border-top:1px solid #fee2e2;margin:18px 0;'>
        <p style='color:#374151;font-size:13.5px;line-height:1.6;'>
            <strong>• On Localhost (WAMP):</strong> Ensure WAMP MySQL is running and database <code>qartiv</code> is imported.<br>
            <strong>• On Vercel:</strong> Add <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code>, and <code>DB_PORT</code> to your Vercel Environment Variables.
        </p>
    </div>");
}

$conn->set_charset("utf8mb4");

?>