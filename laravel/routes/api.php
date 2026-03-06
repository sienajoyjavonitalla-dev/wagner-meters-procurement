<?php

use App\Http\Controllers\Api\ProcurementController;
use Illuminate\Support\Facades\Route;

// All procurement API routes require auth (session). For token-based clients, install laravel/sanctum and use auth:sanctum.
Route::middleware('auth')->group(function () {
    Route::get('/procurement/summary', [ProcurementController::class, 'summary']);
    Route::get('/procurement/queue', [ProcurementController::class, 'queue']);
    Route::get('/procurement/price-comparison', [ProcurementController::class, 'priceComparison']);
    Route::get('/procurement/evidence', [ProcurementController::class, 'evidence']);
    Route::get('/procurement/vendor-progress', [ProcurementController::class, 'vendorProgress']);
    Route::get('/procurement/mapping-review', [ProcurementController::class, 'mappingReview']);
    Route::get('/procurement/system-health', [ProcurementController::class, 'systemHealth']);

    Route::post('/procurement/run', [ProcurementController::class, 'triggerRun']);
    Route::get('/procurement/run-status', [ProcurementController::class, 'runStatus']);
});
