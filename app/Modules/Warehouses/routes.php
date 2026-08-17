<?php

use App\Modules\Warehouses\Http\Controllers\ProductStockController;
use App\Modules\Warehouses\Http\Controllers\TransferController;
use App\Modules\Warehouses\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::apiResource('warehouses', WarehouseController::class);
    Route::post('products/{product}/stock', [ProductStockController::class, 'store']);
    Route::get('transfers', [TransferController::class, 'index']);
    Route::post('transfers', [TransferController::class, 'store']);
});
