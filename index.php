<?php
/**
 * WP Hosting Panel - Main Router
 * API requests go to api/ folder
 * Everything else serves the prototype UI
 */

// Load config and init database
require_once __DIR__ . '/config.php';
initDatabase();

// Route API requests
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

if (strpos($path, '/api/') === 0) {
    $apiFile = __DIR__ . $path . '.php';
    if (file_exists($apiFile)) {
        require_once $apiFile;
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'API endpoint not found']);
    }
    exit;
}

// Serve prototype.html as the frontend
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/prototype.html');
