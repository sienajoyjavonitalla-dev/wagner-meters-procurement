<?php

use App\Http\Controllers\DataImportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('app'));

Route::get('/dashboard', function () {
    return view('app');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboard/{path}', function () {
    return view('app');
})->where('path', '.*')->middleware(['auth', 'verified']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', fn () => view('app'))->name('profile.edit');
    Route::get('/profile/full', [ProfileController::class, 'edit'])->name('profile.edit.full');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/data-import', fn () => view('app'))->name('data-import.create');
    Route::post('/data-import', [DataImportController::class, 'store'])->name('data-import.store');
});

require __DIR__.'/auth.php';
