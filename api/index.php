<?php
/**
 * Vercel Serverless Entry Point Router for QARTIV
 * Routes incoming HTTP requests to their respective PHP scripts while preserving execution context.
 */

// Prevent aggressive CDN and browser caching of dynamic PHP responses
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = ltrim($path, '/');

// Base directory is the project root
$rootDir = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

// Default route (homepage)
if ($path === '' || $path === 'index.php') {
    chdir($rootDir);
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $rootDir . '/index.php';
    require $rootDir . '/index.php';
    exit;
}

// Target file resolution
$targetFile = realpath($rootDir . '/' . $path);
if (!$targetFile) {
    $targetFile = $rootDir . '/' . $path;
}

// Security check: ensure target is a valid PHP file
if (file_exists($targetFile) && is_file($targetFile) && pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
    chdir(dirname($targetFile));
    $_SERVER['SCRIPT_NAME'] = '/' . $path;
    $_SERVER['SCRIPT_FILENAME'] = $targetFile;
    require $targetFile;
    exit;
}

// 404 handler
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body style='font-family:sans-serif;padding:40px;text-align:center;'><h1>404 Not Found</h1><p>The requested page was not found.</p><p><a href='/'>Return to Storefront</a></p></body></html>";
