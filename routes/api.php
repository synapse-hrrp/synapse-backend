<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\VisiteController;


Route::prefix('v1')->group(function () {

    Route::post('auth/login',  [AuthController::class, 'login']);
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

    // Patients (toutes versionnées sous /api/v1/patients)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('patients',                [PatientController::class, 'index'])->middleware('ability:patients.read')->name('v1.patients.index');
        Route::post('patients',               [PatientController::class, 'store'])->middleware('ability:patients.write')->name('v1.patients.store');
        Route::get('patients/{patient}',      [PatientController::class, 'show'])->middleware('ability:patients.read')->name('v1.patients.show');
        Route::patch('patients/{patient}',    [PatientController::class, 'update'])->middleware('ability:patients.write')->name('v1.patients.update');
        Route::delete('patients/{patient}',   [PatientController::class, 'destroy'])->middleware('ability:patients.write')->name('v1.patients.destroy');
        Route::post('patients/{id}/restore',  [PatientController::class, 'restore'])->middleware('ability:patients.write')->name('v1.patients.restore');
        Route::get('patients/{id}/history',   [PatientController::class, 'history'])->middleware('ability:patients.audit')->name('v1.patients.history');
    });


    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('visites',            [VisiteController::class,'index'])->middleware('ability:visites.read')->name('v1.visites.index');
        Route::get('visites/{id}',       [VisiteController::class,'show'])->middleware('ability:visites.read')->name('v1.visites.show');
        Route::post('visites',           [VisiteController::class,'store'])->middleware('ability:visites.write')->name('v1.visites.store');
        Route::patch('visites/{id}',     [VisiteController::class,'update'])->middleware('ability:visites.write')->name('v1.visites.update');
    });
});
