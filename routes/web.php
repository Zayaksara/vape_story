<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    // === CATEGORY ROUTES ===
    Route::resource('categories', \App\Http\Controllers\CategoryController::class)
        ->except(['show']);
    // === BRAND ROUTES ===
    Route::resource('brands', \App\Http\Controllers\BrandController::class)
        ->except(['show']);
    // === PRODUCT ROUTES ===
    Route::resource('products', \App\Http\Controllers\ProductController::class)
        ->except(['show']);
    
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/',                   [ReturnController::class, 'index'])   ->name('index');
        Route::get('/{return}',           [ReturnController::class, 'show'])    ->name('show');
        Route::post('/',                  [ReturnController::class, 'store'])   ->name('store');
        Route::patch('/{return}/approve', [ReturnController::class, 'approve'])->name('approve');
        Route::patch('/{return}/reject',  [ReturnController::class, 'reject']) ->name('reject');
    });

    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/',          [OrderController::class, 'index']) ->name('index');
        Route::get('/{order}',   [OrderController::class, 'show'])  ->name('show');
        Route::post('/',         [OrderController::class, 'store']) ->name('store');
        Route::patch('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
    });
});

require __DIR__.'/settings.php';

