<?php

use App\Http\Controllers\Api\V1\CatalogueController;
use App\Http\Controllers\Api\V1\MachineController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StockMovementController;
use Illuminate\Support\Facades\Route;

/*
| Read-only API v1 (SPEC 7a). Bearer token of an active API client with the "read" ability,
| rate limited per token. Every answer is limited to the client's site when it has one.
*/

// The OpenAPI description is public: it holds no data.
Route::get('/v1/openapi.yaml', fn () => response(file_get_contents(resource_path('api/openapi.yaml')), 200, ['Content-Type' => 'application/yaml']))
    ->name('api.openapi');

Route::prefix('v1')->name('api.')->middleware(['auth:sanctum', 'api.client:read', 'throttle:api'])->group(function () {
    Route::get('/sites', [CatalogueController::class, 'sites'])->name('sites');
    Route::get('/categories', [CatalogueController::class, 'categories'])->name('categories');
    Route::get('/items', [CatalogueController::class, 'items'])->name('items');
    Route::get('/items/{item}', [CatalogueController::class, 'item'])->whereNumber('item')->name('item');
    Route::get('/suppliers', [CatalogueController::class, 'suppliers'])->name('suppliers');
    Route::get('/suppliers/{supplier}', [CatalogueController::class, 'supplier'])->whereNumber('supplier')->name('supplier');

    Route::get('/stock', [StockController::class, 'index'])->name('stock');

    Route::get('/machine-types', [MachineController::class, 'types'])->name('machine-types');
    Route::get('/machine-types/{machineType:code}', [MachineController::class, 'type'])->name('machine-type');
    Route::get('/machines', [MachineController::class, 'index'])->name('machines');
    Route::get('/machines/{machine:sku}', [MachineController::class, 'show'])->name('machine');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders');
    Route::get('/purchase-orders/{purchaseOrder:number}', [PurchaseOrderController::class, 'show'])->name('purchase-order');

    Route::get('/stock-movements', [StockMovementController::class, 'index'])->name('stock-movements');
});
