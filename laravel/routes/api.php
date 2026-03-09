<?php

use App\Http\Controllers\Api\ProcurementController;
use Illuminate\Support\Facades\Route;

// These endpoints are consumed by the React SPA using the same session cookie as the web app.
// Laravel's default API middleware group is stateless; add 'web' so session auth works.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/procurement/summary', [ProcurementController::class, 'summary']);
    Route::get('/procurement/analytics', [ProcurementController::class, 'analytics']);
    Route::get('/procurement/queue', [ProcurementController::class, 'queue']);
    Route::get('/procurement/price-comparison', [ProcurementController::class, 'priceComparison']);
    Route::get('/procurement/evidence', [ProcurementController::class, 'evidence']);
    Route::get('/procurement/vendor-progress', [ProcurementController::class, 'vendorProgress']);
    Route::get('/procurement/mapping-review', [ProcurementController::class, 'mappingReview']);
    Route::get('/procurement/system-health', [ProcurementController::class, 'systemHealth']);
    Route::get('/procurement/settings', [ProcurementController::class, 'settings']);
    Route::get('/procurement/users', [ProcurementController::class, 'users'])->middleware('can:manage-users');
    Route::post('/procurement/users', [ProcurementController::class, 'createUser'])->middleware('can:manage-users');
    Route::patch('/procurement/users/{user}', [ProcurementController::class, 'updateUser'])->middleware('can:manage-users');
    Route::delete('/procurement/users/{user}', [ProcurementController::class, 'deleteUser'])->middleware('can:manage-users');

    Route::post('/procurement/run', [ProcurementController::class, 'triggerRun'])->middleware('can:manage-procurement');
    Route::post('/procurement/settings', [ProcurementController::class, 'updateSettings'])->middleware('can:manage-procurement');
    Route::patch('/procurement/users/{user}/role', [ProcurementController::class, 'updateUserRole'])->middleware('can:manage-users');
    Route::get('/procurement/run-status', [ProcurementController::class, 'runStatus']);
});
