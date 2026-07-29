<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:5,1']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Dashboard routes
Route::middleware('auth')->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
});

// Document routes
Route::middleware('auth')->prefix('documents')->name('documents.')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('index');
    Route::get('/create', [DocumentController::class, 'create'])->name('create');
    Route::post('/upload', [DocumentController::class, 'upload'])->name('upload');
    Route::get('/status/{id}', [DocumentController::class, 'getStatus'])->name('status');
    Route::delete('/{id}', [DocumentController::class, 'delete'])->name('delete');
    Route::get('/search', [DocumentController::class, 'search'])->name('search');
    Route::get('/{id}', [DocumentController::class, 'show'])->name('show');
});
