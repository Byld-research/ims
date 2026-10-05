<?php

use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemSearchController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MachineTypeController;
use App\Http\Controllers\MachineTypeItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderActionController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderLineController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SiteSelectionController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockLevelController;
use App\Http\Controllers\StockLookupController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierItemController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::post('/site', SiteSelectionController::class)->name('site.select');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Catalogue
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/lookup', StockLookupController::class)->name('stock.lookup');
    Route::get('/stock/levels', [StockLevelController::class, 'edit'])->name('stock.levels');
    Route::put('/stock/levels', [StockLevelController::class, 'update'])->name('stock.levels.update');
    Route::get('/issues/create', [IssueController::class, 'create'])->name('issues.create');
    Route::post('/issues', [IssueController::class, 'store'])->name('issues.store');
    Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::get('/adjustments/create', [AdjustmentController::class, 'create'])->name('adjustments.create');
    Route::post('/adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
    Route::get('/items/search', ItemSearchController::class)->name('items.search');
    Route::resource('items', ItemController::class)->except(['index', 'destroy']);
    Route::resource('categories', CategoryController::class)->except(['show', 'destroy']);

    // Suppliers
    Route::resource('suppliers', SupplierController::class)->except('destroy');
    Route::post('/suppliers/{supplier}/items', [SupplierItemController::class, 'store'])->name('suppliers.items.store');
    Route::put('/supplier-items/{supplierItem}', [SupplierItemController::class, 'update'])->name('supplier-items.update');
    Route::delete('/supplier-items/{supplierItem}', [SupplierItemController::class, 'destroy'])->name('supplier-items.destroy');

    // Machine types and parts lists
    Route::resource('machine-types', MachineTypeController::class)->except('destroy')->parameters(['machine-types' => 'machineType']);
    Route::post('/machine-types/{machineType}/items', [MachineTypeItemController::class, 'store'])->name('machine-types.items.store');
    Route::post('/machine-types/{machineType}/import', [MachineTypeItemController::class, 'import'])->name('machine-types.import');
    Route::put('/machine-type-items/{machineTypeItem}', [MachineTypeItemController::class, 'update'])->name('machine-type-items.update');
    Route::delete('/machine-type-items/{machineTypeItem}', [MachineTypeItemController::class, 'destroy'])->name('machine-type-items.destroy');

    // Purchase orders
    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show', 'update'])
        ->parameters(['purchase-orders' => 'purchaseOrder']);
    Route::post('/purchase-orders/{purchaseOrder}/lines', [PurchaseOrderLineController::class, 'store'])->name('purchase-orders.lines.store');
    Route::put('/purchase-order-lines/{purchaseOrderLine}', [PurchaseOrderLineController::class, 'update'])->name('purchase-order-lines.update');
    Route::delete('/purchase-order-lines/{purchaseOrderLine}', [PurchaseOrderLineController::class, 'destroy'])->name('purchase-order-lines.destroy');
    Route::post('/purchase-order-lines/{purchaseOrderLine}/close-short', [PurchaseOrderActionController::class, 'closeShort'])->name('purchase-order-lines.close-short');
    foreach (['order', 'confirm', 'ship', 'cancel', 'close'] as $action) {
        Route::post("/purchase-orders/{purchaseOrder}/{$action}", [PurchaseOrderActionController::class, $action])->name("purchase-orders.{$action}");
    }
    Route::get('/purchase-orders/{purchaseOrder}/receive', [ReceiptController::class, 'create'])->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [ReceiptController::class, 'store'])->name('purchase-orders.receive.store');

    // Stock counts
    Route::resource('stock-counts', StockCountController::class)->only(['index', 'create', 'store', 'show'])
        ->parameters(['stock-counts' => 'stockCount']);
    Route::post('/stock-counts/{stockCount}/lines', [StockCountController::class, 'addLines'])->name('stock-counts.lines.store');
    Route::delete('/stock-count-lines/{stockCountLine}', [StockCountController::class, 'removeLine'])->name('stock-count-lines.destroy');
    Route::post('/stock-counts/{stockCount}/start', [StockCountController::class, 'start'])->name('stock-counts.start');
    Route::put('/stock-counts/{stockCount}/counts', [StockCountController::class, 'saveCounts'])->name('stock-counts.counts');
    Route::get('/stock-counts/{stockCount}/review', [StockCountController::class, 'review'])->name('stock-counts.review');
    Route::post('/stock-counts/{stockCount}/post', [StockCountController::class, 'post'])->name('stock-counts.post');
    Route::post('/stock-counts/{stockCount}/cancel', [StockCountController::class, 'cancel'])->name('stock-counts.cancel');

    // Machine register
    Route::resource('machines', MachineController::class)->except('destroy');
});

require __DIR__.'/auth.php';
