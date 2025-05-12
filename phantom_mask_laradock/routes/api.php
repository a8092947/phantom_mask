<?php

use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

// 藥局相關路由
Route::prefix('pharmacies')->group(function () {
    Route::get('/', [PharmacyController::class, 'index']);
    Route::get('/{id}', [PharmacyController::class, 'show']);
});

// 交易相關路由
Route::prefix('transactions')->group(function () {
    Route::get('/top-users', [TransactionController::class, 'topUsers']);
    Route::get('/statistics', [TransactionController::class, 'statistics']);
    Route::post('/', [TransactionController::class, 'store']);
});