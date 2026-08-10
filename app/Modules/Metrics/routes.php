<?php

use App\Modules\Metrics\Http\Controllers\SalesMetricsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'scope.warehouse'])->group(function (): void {
    Route::get('metrics/sales', SalesMetricsController::class)->middleware('can:metrics.view');
});
