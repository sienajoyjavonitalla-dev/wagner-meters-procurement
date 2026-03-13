<?php

use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProcurementController;
use App\Http\Controllers\Api\ResearchedMpnController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// These endpoints are consumed by the React SPA using the same session cookie as the web app.
// Laravel's default API middleware group is stateless; add 'web' so session auth works.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/procurement/inventories', [InventoryController::class, 'index']);
    Route::post('/procurement/inventories/{inventory}/clear-research', [InventoryController::class, 'clearResearch'])->middleware('can:manage-procurement');
    Route::post('/procurement/inventories/clear-research-all', [InventoryController::class, 'clearAllResearch'])->middleware('can:manage-procurement');
    Route::get('/procurement/researched-mpn', [ResearchedMpnController::class, 'index'])->middleware('can:manage-procurement');
    Route::get('/procurement/summary', [ProcurementController::class, 'summary']);
    Route::get('/procurement/analytics', [ProcurementController::class, 'analytics']);
    Route::get('/procurement/queue', [ProcurementController::class, 'queue']);
    Route::get('/procurement/price-comparison', [ProcurementController::class, 'priceComparison']);
    Route::get('/procurement/evidence', [ProcurementController::class, 'evidence']);
    Route::get('/procurement/vendor-progress', [ProcurementController::class, 'vendorProgress']);
    Route::get('/procurement/mapping-review', [ProcurementController::class, 'mappingReview']);
    Route::get('/procurement/system-health', [ProcurementController::class, 'systemHealth']);
    Route::get('/procurement/settings', [ProcurementController::class, 'settings']);
    Route::get('/procurement/users', [UserController::class, 'index'])->middleware('can:manage-users');
    Route::post('/procurement/users', [UserController::class, 'store'])->middleware('can:manage-users');
    Route::patch('/procurement/users/{user}', [UserController::class, 'update'])->middleware('can:manage-users');
    Route::delete('/procurement/users/{user}', [UserController::class, 'destroy'])->middleware('can:manage-users');

    Route::post('/procurement/run', [ProcurementController::class, 'triggerRun'])->middleware('can:manage-procurement');
    Route::post('/procurement/settings', [ProcurementController::class, 'updateSettings'])->middleware('can:manage-procurement');
    Route::patch('/procurement/users/{user}/role', [UserController::class, 'updateRole'])->middleware('can:manage-users');
    Route::get('/procurement/run-status', [ProcurementController::class, 'runStatus']);
});
