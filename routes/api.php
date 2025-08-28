<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\PersonnelController;

Route::prefix('v1')->group(function () {

    // Auth (publiques pour login)
    Route::post('auth/login', [AuthController::class, 'login']);

    // Auth (protégées)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me',     [AuthController::class, 'me']);
    });

    // Admin (protégé par Sanctum + rôle admin) => /api/v1/admin/...
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:admin'])
        ->group(function () {

            // Users (REST complet)
            Route::apiResource('users', UserManagementController::class)
                ->parameters(['users' => 'user']);

            // Personnels (REST complet)
            Route::apiResource('personnels', PersonnelController::class)
                ->parameters(['personnels' => 'personnel']);
        });
});
