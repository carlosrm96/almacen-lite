<?php

use App\Modules\Warehouses\Http\Controllers\ProductStockController;
use App\Modules\Warehouses\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('warehouses', WarehouseController::class);
    Route::post('products/{product}/stock', [ProductStockController::class, 'store']);
});
