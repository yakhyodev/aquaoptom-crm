<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\EventInvalidationApiController;
use App\Http\Controllers\Api\OperationApiController;
use App\Http\Controllers\Api\SyncApiController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — AquaOptom Wholesale Beverage CRM
|--------------------------------------------------------------------------
*/

// Public Authentication & Health Ping
Route::get('/health', [SyncApiController::class, 'ping']);
Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:login');

// Protected API Routes (Sanctum Token + Active User Check)
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Auth Management
    Route::post('/auth/logout', [AuthApiController::class, 'logout']);
    Route::get('/auth/me', [AuthApiController::class, 'me']);

    // Catalog & Stock
    Route::get('/products', [ApiController::class, 'getProducts']);

    // Inward Receiving (Requires receive_stock permission or Owner/Admin/Warehouse)
    Route::post('/inward', [ApiController::class, 'storeInward'])
        ->middleware('permission:receive_stock');

    // Sales (Requires active cashier / seller / owner)
    Route::post('/sales', [ApiController::class, 'storeSale']);
    Route::get('/sales/history', [ApiController::class, 'getSalesHistory']);

    // Dashboard & Reports
    Route::get('/dashboard', [ApiController::class, 'dashboard']);
    Route::get('/reports', [ApiController::class, 'getReports']);

    // Customers & Suppliers
    Route::get('/customers', [ApiController::class, 'getCustomers']);
    Route::post('/customers', [ApiController::class, 'storeCustomer']);
    Route::get('/suppliers', [ApiController::class, 'getSuppliers']);
    Route::post('/suppliers', [ApiController::class, 'storeSupplier']);

    // Cash Accounts & Payments
    Route::get('/cash-accounts', [ApiController::class, 'getCashAccounts']);
    Route::post('/payments', [ApiController::class, 'storePayment']);

    // Profit / Cost Calculator
    Route::post('/calculator', [ApiController::class, 'calculate'])
        ->middleware('permission:view_cost_price');

    // Generic Idempotent Operation Runner
    Route::post('/operations/execute', [OperationApiController::class, 'execute']);

    // Sync Protocol Endpoints (Prompt 12 & 14)
    Route::get('/sync/health', [SyncApiController::class, 'health']);
    Route::post('/sync/bootstrap', [SyncApiController::class, 'bootstrap']);
    Route::get('/sync/pull', [SyncApiController::class, 'pull']);
    Route::post('/sync/push', [SyncApiController::class, 'push']);
    Route::get('/sync/status/{operation_id}', [SyncApiController::class, 'status']);
    Route::get('/sync/conflicts', [SyncApiController::class, 'conflicts'])
        ->middleware('permission:manage_devices');
    Route::post('/sync/conflicts/{id}/resolve', [SyncApiController::class, 'resolveConflict'])
        ->middleware('permission:manage_devices');

    // Event Invalidation for Mobile (Flutter) and PWA (Prompt 18)
    Route::get('/events/invalidation', [EventInvalidationApiController::class, 'index']);
});

// Telegram Bot Webhook
Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);
