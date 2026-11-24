<?php

use App\Http\Controllers\AuthController;
use App\Services\StorageRepository;

/**
 * Takashi Fried Chicken (TFC) - Complete REST API Route Map
 * Dispatched by public/index.php & root index.php
 */

return [
    'POST /api/login'                        => [AuthController::class, 'login'],
    'POST /api/logout'                       => [AuthController::class, 'logout'],
    'GET  /api/bootstrap'                    => [StorageRepository::class, 'getBootstrapState'],
    'GET  /api/outlets'                      => 'Master 5 Fasilitas Takashi Palu',
    'GET  /api/configs'                      => [StorageRepository::class, 'getConfigs'],
    'PUT  /api/configs'                      => [StorageRepository::class, 'updateConfigs'],
    'GET  /api/items'                        => [StorageRepository::class, 'getItems'],
    'GET  /api/batches'                      => 'FEFO Batches Inventory',
    'POST /api/receiving'                    => [StorageRepository::class, 'recordReceiving'],
    'GET  /api/kitchen-logs'                 => 'Kitchen Sales & Spoilage Logs',
    'POST /api/kitchen-logs'                 => [StorageRepository::class, 'recordKitchenLog'],
    'POST /api/holding-cabinets'             => [StorageRepository::class, 'addHoldingCabinet'],
    'POST /api/holding-cabinets/{id}/discard'=> [StorageRepository::class, 'discardHoldingCabinet'],
    'GET  /api/transfers'                    => 'Inter-Branch Stock Transfers',
    'POST /api/transfers'                    => [StorageRepository::class, 'createTransfer'],
    'PUT  /api/transfers/{id}/approve'       => [StorageRepository::class, 'updateTransferStatus'],
    'PUT  /api/transfers/{id}/reject'        => [StorageRepository::class, 'updateTransferStatus'],
    'PUT  /api/transfers/{id}/complete'      => [StorageRepository::class, 'updateTransferStatus'],
    'GET  /api/purchase-orders'              => 'Purchase Orders Formal (SCR-09)',
    'POST /api/purchase-orders'              => [StorageRepository::class, 'createPurchaseOrder'],
    'PUT  /api/purchase-orders/{id}/status'  => [StorageRepository::class, 'updatePurchaseOrderStatus'],
    'GET  /api/stock-opnames'                => 'Stock Opname Formal (SCR-10)',
    'POST /api/stock-opnames'                => [StorageRepository::class, 'createStockOpname'],
    'GET  /api/telemetry'                    => 'IoT Sensor TPM & Chiller Telemetry',
];
