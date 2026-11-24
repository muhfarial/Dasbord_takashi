<?php

declare(strict_types=1);

/**
 * Takashi Fried Chicken (TFC) - Entry Point & REST API Front Controller
 * Architecture: React 18 SPA + PHP 8.3 Laravel MVC Pattern + Dual-PDO (MySQL 8.0 / SQLite)
 */

// Allow CORS for local development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload application classes (PSR-4)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Extract API sub-path cleanly whether hosted on root (/api/...) or subdirectory (/dasbord takasih/api/...)
if (preg_match('#/api(/.*)$#', $requestUri, $apiMatch)) {
    header('Content-Type: application/json; charset=utf-8');
    $apiPath = rtrim($apiMatch[1], '/');

    $rawInput = file_get_contents('php://input') ?: '';
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    try {
        // 1. Authentication Routes (bcrypt + password_verify)
        if ($apiPath === '/login' && $method === 'POST') {
            $controller = new \App\Http\Controllers\AuthController();
            echo json_encode($controller->login($input), JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($apiPath === '/logout' && $method === 'POST') {
            $controller = new \App\Http\Controllers\AuthController();
            echo json_encode($controller->logout(), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Initialize Persistent PDO Storage Repository
        $repo = new \App\Services\StorageRepository();

        // 2. Bootstrap Full Live State (Single Fast Request for SPA)
        if ($apiPath === '/bootstrap' && $method === 'GET') {
            echo json_encode($repo->getBootstrapState(), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3. Outlets Master
        if ($apiPath === '/outlets' && $method === 'GET') {
            echo json_encode([
                ['id' => 'all', 'code' => 'TFC-ALL', 'name' => 'Semua Cabang (Konsolidasi)', 'type' => 'CONSOLIDATED'],
                ['id' => 'out-01', 'code' => 'TFC-HQ', 'name' => 'Central Plant & Cold Storage Hub', 'type' => 'CENTRAL_PLANT', 'address' => 'Kawasan Pusat Suplai & Cold Storage'],
                ['id' => 'out-02', 'code' => 'TFC-DYD', 'name' => 'Takashi fried chicken DayoDara', 'type' => 'FAST_FOOD', 'address' => 'Jalan Dayo Dara, Talise Valangguni', 'hours' => 'Open · Closes 00:00'],
                ['id' => 'out-03', 'code' => 'TFC-ZBR', 'name' => 'Takashi fried chicken (Zebra)', 'type' => 'CHICKEN', 'address' => 'Jalan Zebra, Lolu Utara'],
                ['id' => 'out-04', 'code' => 'TFC-LPT', 'name' => 'Takashi Fried Chicken (Lapatta)', 'type' => 'RESTAURANT', 'address' => 'Jalan Lapata, Dusun 4'],
                ['id' => 'out-05', 'code' => 'TFC-NGR', 'name' => 'Takashi Fried Chicken (Ngurah Rai)', 'type' => 'RESTAURANT', 'address' => 'Jalan I Gusti Ngurah Rai, Boyaoge'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 4. Configs (GET & PUT)
        if ($apiPath === '/configs' && $method === 'GET') {
            echo json_encode($repo->getConfigs(), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($apiPath === '/configs' && in_array($method, ['PUT', 'POST'], true)) {
            echo json_encode($repo->updateConfigs($input), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 5. Items & FEFO Batches
        if ($apiPath === '/items' && $method === 'GET') {
            echo json_encode($repo->getItems(), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($apiPath === '/batches' && $method === 'GET') {
            echo json_encode($repo->getBootstrapState()['fefoBatches'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 6. Goods Receiving & Cold-Chain Gate (POST)
        if ($apiPath === '/receiving' && $method === 'POST') {
            echo json_encode($repo->recordReceiving($input), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 7. Kitchen Logs (GET & POST)
        if ($apiPath === '/kitchen-logs' && $method === 'GET') {
            echo json_encode($repo->getBootstrapState()['kitchenLogs'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($apiPath === '/kitchen-logs' && $method === 'POST') {
            echo json_encode($repo->recordKitchenLog($input), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 8. Holding Cabinets (POST add & POST/DELETE discard)
        if ($apiPath === '/holding-cabinets' && $method === 'POST') {
            echo json_encode($repo->addHoldingCabinet($input), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (preg_match('#^/holding-cabinets/([^/]+)(?:/discard)?$#', $apiPath, $m) && in_array($method, ['POST', 'DELETE'], true)) {
            echo json_encode($repo->discardHoldingCabinet(urldecode($m[1]), (string)($input['user'] ?? 'Agus Koki')), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 9. Inter-Branch Transfers (GET, POST, PUT approve/reject/complete)
        if ($apiPath === '/transfers' && $method === 'GET') {
            echo json_encode($repo->getBootstrapState()['transfers'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($apiPath === '/transfers' && $method === 'POST') {
            echo json_encode($repo->createTransfer($input), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (preg_match('#^/transfers/([^/]+)/(approve|reject|complete)$#', $apiPath, $m) && in_array($method, ['PUT', 'POST'], true)) {
            $role = strtoupper((string)($input['role'] ?? ''));
            $reason = isset($input['reason']) ? (string)$input['reason'] : null;
            echo json_encode($repo->updateTransferStatus(urldecode($m[1]), $m[2], $role, $reason), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 10. Purchase Orders Formal (GET, POST, PUT status)
        if ($apiPath === '/purchase-orders' && $method === 'GET') {
            echo json_encode($repo->getBootstrapState()['purchaseOrders'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($apiPath === '/purchase-orders' && $method === 'POST') {
            echo json_encode($repo->createPurchaseOrder($input), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (preg_match('#^/purchase-orders/([^/]+)/status$#', $apiPath, $m) && in_array($method, ['PUT', 'POST'], true)) {
            $newStatus = strtoupper((string)($input['status'] ?? ''));
            $actor = (string)($input['actor'] ?? 'Staff');
            $role = strtoupper((string)($input['role'] ?? ''));
            echo json_encode($repo->updatePurchaseOrderStatus(urldecode($m[1]), $newStatus, $actor, $role), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 11. Stock Opname Formal (GET & POST)
        if ($apiPath === '/stock-opnames' && $method === 'GET') {
            echo json_encode($repo->getBootstrapState()['stockOpnames'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($apiPath === '/stock-opnames' && $method === 'POST') {
            echo json_encode($repo->createStockOpname($input), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 12. IoT Telemetry (GET)
        if ($apiPath === '/telemetry' && $method === 'GET') {
            $file = __DIR__ . '/../database/datasets_json/iot_telemetry.json';
            if (file_exists($file)) {
                readfile($file);
                exit;
            }
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'API Endpoint not found: ' . $apiPath], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Serve React SPA (index.html)
$indexPath = __DIR__ . '/../index.html';
if (file_exists($indexPath)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($indexPath);
    exit;
}

echo "Takashi Fried Chicken System - React Frontend Loading...";
