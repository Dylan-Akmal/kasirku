<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/password', [AuthController::class, 'changePassword']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::get('/transactions/{transaction}/receipt', [TransactionController::class, 'receipt']);

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class)->only(['index', 'store', 'update']);
        Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);

        Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
        Route::post('/products/{product}/image', [ProductController::class, 'uploadImage']);
        Route::delete('/products/{product}/image', [ProductController::class, 'deleteImage']);

        Route::post('/products/{product}/stock-in', [StockController::class, 'stockIn']);
        Route::post('/products/{product}/stock-adjust', [StockController::class, 'adjust']);
        Route::get('/stock-movements', [StockController::class, 'index']);

        Route::post('/transactions/{transaction}/void', [TransactionController::class, 'void']);
    });
});