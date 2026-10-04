<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/password', [AuthController::class, 'changePassword']);

    Route::get('/categories', [CategoryController::class, 'index']);

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class)->only(['index', 'store', 'update']);
        Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
    });
});