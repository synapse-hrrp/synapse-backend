<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\PersonnelController;

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });

    // ⬇️ Enlève "role:admin" ici pour l’instant
    Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
        Route::get('users',  [UserManagementController::class, 'index']);
        Route::post('users', [UserManagementController::class, 'store']);
    });


    Route::middleware(['auth:sanctum', 'role:admin'])
        ->prefix('v1/admin')
        ->group(function () {
            Route::apiResource('personnels', PersonnelController::class)->parameters([
                'personnels' => 'personnel'
            ]);
        });
});
