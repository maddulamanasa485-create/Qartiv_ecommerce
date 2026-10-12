<?php
/**
 * QARTIV - Database Connection Configuration
 * Supports Localhost (WAMP/XAMPP) & Live Cloud Hosting (InfinityFree, cPanel, etc.)
 */

// Local Database Settings (Default for WAMP)
$host = "localhost";
$user = "root";
$password = "";
$database = "qartiv";

// Live Cloud Hosting / Environment Overrides
// When deploying to InfinityFree or other live hosts, you can set your credentials here:
if (getenv('DB_HOST')) {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $password = getenv('DB_PASS');
    $database = getenv('DB_NAME');
}

$conn = @new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("<div style='font-family:Segoe UI,sans-serif;padding:30px;max-width:620px;margin:50px auto;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;color:#991b1b;box-shadow:0 10px 25px rgba(0,0,0,0.05);'>
        <h2 style='margin-top:0;font-size:20px;'>⚠️ Database Connection Error</h2>
        <p style='font-size:15px;line-height:1.5;'>Could not connect to the database: <code>" . htmlspecialchars($conn->connect_error) . "</code></p>
        <hr style='border:none;border-top:1px solid #fee2e2;margin:18px 0;'>
        <p style='color:#374151;font-size:13.5px;line-height:1.6;'>
            <strong>• On Localhost (WAMP):</strong> Make sure the WAMP MySQL service is running (green icon) and the database <code>qartiv</code> is imported.<br>
            <strong>• On Live Server (InfinityFree):</strong> Open <code>config/db.php</code> and fill in your MySQL Hostname, Username, Password, and Database from your hosting panel.
        </p>
    </div>");
}

$conn->set_charset("utf8mb4");

?>