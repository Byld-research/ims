<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemSearchController;
use App\Http\Controllers\MachineTypeController;
use App\Http\Controllers\MachineTypeItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteSelectionController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierItemController;
use App\Http\Controllers\WorkCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::post('/site', SiteSelectionController::class)->name('site.select');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Catalogue
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
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

    // Work centres
    Route::resource('work-centers', WorkCenterController::class)->except('destroy')->parameters(['work-centers' => 'workCenter']);
});

require __DIR__.'/auth.php';
