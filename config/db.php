<?php
/**
 * QARTIV - Database Connection Configuration
 * Secure multi-environment database loader with robust error handling for Vercel Serverless.
 * Compatible with Localhost (WAMP/XAMPP), TiDB Cloud, Aiven, and Live Hosting.
 */

// Disable default PHP 8.1+ unhandled exceptions so we can catch and handle connection issues gracefully
mysqli_report(MYSQLI_REPORT_OFF);

// Default Local Database Settings (WAMP / Localhost)
$host = "localhost";
$user = "root";
$password = "";
$database = "qartiv";
$port = 3306;

// 1. Cloud & Serverless Environment Variables (Vercel, Render, Railway, Docker)
if (getenv('DB_HOST')) {
    $host = trim(getenv('DB_HOST'));
    $user = getenv('DB_USER') !== false ? trim(getenv('DB_USER')) : 'root';
    $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    $database = getenv('DB_NAME') !== false ? trim(getenv('DB_NAME')) : 'qartiv';
    if (getenv('DB_PORT')) {
        $port = (int)trim(getenv('DB_PORT'));
    }
}

// 2. Private Local Credentials (Untracked in Git for environments without env vars)
$privateConfigFile = __DIR__ . '/db.local.php';
if (file_exists($privateConfigFile)) {
    include $privateConfigFile;
}

// Initialize MySQLi
$conn = mysqli_init();
if (!$conn) {
    die("<div style='font-family:sans-serif;padding:30px;max-width:600px;margin:40px auto;border-radius:10px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;'>
        <h2>Database Engine Error</h2>
        <p>Failed to initialize MySQLi client extension.</p>
    </div>");
}

// Set connection timeout (5 seconds)
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

// Detect if SSL connection is required (TiDB Cloud, Aiven, port 4000, or DB_SSL=true)
$isCloudSsl = (getenv('DB_SSL') === 'true') || ($port === 4000) || (strpos($host, 'tidbcloud.com') !== false) || (strpos($host, 'aivencloud.com') !== false);

$connected = false;
$connectionError = '';

try {
    if ($isCloudSsl) {
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        }
        $flags = defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT') ? MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT : MYSQLI_CLIENT_SSL;
        $connected = @$conn->real_connect($host, $user, $password, $database, $port, NULL, $flags);
        
        // Fallback to standard SSL flag if custom flag failed
        if (!$connected) {
            $connected = @$conn->real_connect($host, $user, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL);
        }
    } else {
        $connected = @$conn->real_connect($host, $user, $password, $database, $port);
    }
} catch (Throwable $e) {
    $connected = false;
    $connectionError = $e->getMessage();
}

if (!$connected) {
    if (empty($connectionError)) {
        $connectionError = $conn->connect_error ?: mysqli_connect_error() ?: "Unable to establish connection to database server.";
    }
    
    // Mask sensitive details
    $maskedUser = strlen($user) > 6 ? substr($user, 0, 3) . '***' . substr($user, -3) : (strlen($user) > 0 ? substr($user, 0, 1) . '***' : 'not set');
    $displayHost = htmlspecialchars($host);
    $displayDb = htmlspecialchars($database);
    $displayPort = (int)$port;
    $cleanError = htmlspecialchars($connectionError);

    die("<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>QARTIV — Database Connection Notice</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0b0f19; color: #f8fafc; padding: 40px 20px; margin: 0; line-height: 1.6; }
            .card { max-width: 650px; margin: 30px auto; background: #131b2e; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 32px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
            .badge { display: inline-block; background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 16px; }
            h2 { margin: 0 0 12px; font-size: 22px; color: #ffffff; }
            .error-box { background: rgba(0,0,0,0.3); border-left: 4px solid #ef4444; padding: 14px 18px; border-radius: 8px; margin: 18px 0; font-family: monospace; font-size: 13px; color: #fca5a5; word-break: break-all; }
            .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0; font-size: 13px; background: rgba(255,255,255,0.03); padding: 14px; border-radius: 10px; }
            .meta-item { color: #94a3b8; }
            .meta-item strong { color: #f1f5f9; display: block; }
            .help-steps { background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 10px; padding: 16px; margin-top: 20px; font-size: 13.5px; color: #cbd5e1; }
            .help-steps ol { margin: 10px 0 0 20px; padding: 0; }
            .help-steps li { margin-bottom: 6px; }
        </style>
    </head>
    <body>
        <div class='card'>
            <div class='badge'>⚠️ Database Configuration Notice</div>
            <h2>Could Not Connect to Database</h2>
            <p style='color:#94a3b8; font-size:14px; margin-bottom:0;'>The application reached Vercel successfully, but the MySQL database connection was refused.</p>
            
            <div class='error-box'>{$cleanError}</div>

            <div class='meta-grid'>
                <div class='meta-item'>Host: <strong>{$displayHost}</strong></div>
                <div class='meta-item'>Port: <strong>{$displayPort}</strong></div>
                <div class='meta-item'>User: <strong>{$maskedUser}</strong></div>
                <div class='meta-item'>Database: <strong>{$displayDb}</strong></div>
            </div>

            <div class='help-steps'>
                <strong>🔧 How to fix in Vercel Project Settings:</strong>
                <ol>
                    <li>Go to <strong>Vercel ➔ Project Settings ➔ Environment Variables</strong>.</li>
                    <li>Verify <code>DB_HOST</code> matches your cloud database gateway.</li>
                    <li>Verify <code>DB_USER</code> (on TiDB Cloud, username must include the prefix, e.g. <code>xxxx.root</code>).</li>
                    <li>Verify <code>DB_PASS</code> contains your exact password without quotes.</li>
                    <li>Verify <code>DB_NAME</code> matches your database name (e.g. <code>test</code> or <code>qartiv</code>).</li>
                    <li>Click <strong>Deployments ➔ Redeploy</strong> to apply changes.</li>
                </ol>
            </div>
        </div>
    </body>
    </html>");
}

$conn->set_charset("utf8mb4");

?>