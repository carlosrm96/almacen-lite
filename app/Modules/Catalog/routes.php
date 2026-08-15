<?php

use App\Modules\Catalog\Http\Controllers\CurrencyController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductUnitController;
use App\Modules\Catalog\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('currencies', [CurrencyController::class, 'index']);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('products', ProductController::class);
    Route::post('products/{product}/units', [ProductUnitController::class, 'store']);
    Route::delete('products/{product}/units/{unit}', [ProductUnitController::class, 'destroy']);
});
