<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\OperationApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — AquaOptom Wholesale Beverage CRM
|--------------------------------------------------------------------------
*/

// Public Authentication
Route::post('/auth/login', [AuthApiController::class, 'login']);

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

    // Profit / Cost Calculator
    Route::post('/calculator', [ApiController::class, 'calculate'])
        ->middleware('permission:view_cost_price');

    // Generic Idempotent Operation Runner
    Route::post('/operations/execute', [OperationApiController::class, 'execute']);
});
