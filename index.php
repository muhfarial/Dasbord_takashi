<?php
declare(strict_types=1);

/**
 * Takashi Fried Chicken (TFC) - Root Entry Point & Front Controller
 * Architecture: React + Laravel/PHP + MySQL
 */

// Allow CORS for local development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Check if request is for API
if (str_contains($requestUri, '/api/')) {
    require_once __DIR__ . '/public/index.php';
    exit;
}

// Serve React SPA (index.html) with Strict Login Gateway
$indexPath = __DIR__ . '/index.html';
if (file_exists($indexPath)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($indexPath);
    exit;
}

echo "Takashi Fried Chicken System - React Frontend Loading...";
