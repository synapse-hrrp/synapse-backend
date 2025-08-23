<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserManagementController;

Route::prefix('v1')->group(function () {

    Route::post('auth/login',  [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me',     [AuthController::class, 'me']);
    });

    // ❗ ICI: alias 'role:admin' (pas la classe)
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('users',  [UserManagementController::class, 'index']);
        Route::post('users', [UserManagementController::class, 'store']);
    });
});
