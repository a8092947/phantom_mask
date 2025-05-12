<?php

use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\MaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

// 藥局相關路由
Route::prefix('pharmacies')->group(function () {
    Route::get('/', [PharmacyController::class, 'index']);
    Route::get('/{id}', [PharmacyController::class, 'show']);
});

// 口罩相關路由
Route::prefix('masks')->group(function () {
    Route::get('/', [MaskController::class, 'index']);
    Route::get('/{id}', [MaskController::class, 'show']);
    Route::get('/search', [MaskController::class, 'search']);
});

// 用戶相關路由
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::get('/top', [UserController::class, 'top']);
    Route::get('/search', [UserController::class, 'search']);
});

// 交易相關路由
Route::prefix('transactions')->group(function () {
    Route::get('/', [TransactionController::class, 'index']);
    Route::get('/stats', [TransactionController::class, 'stats']);
    Route::post('/', [TransactionController::class, 'store']);
});