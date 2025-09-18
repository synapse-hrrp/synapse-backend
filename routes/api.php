<?php

use Illuminate\Support\Facades\Route;


// ── Controllers principaux ───────────────────────────────────────────────
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\PersonnelController;
use App\Http\Controllers\Api\ServiceController;

use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\VisiteController;
use App\Http\Controllers\Api\LaboratoireController;
use App\Http\Controllers\Api\PansementController;

// Finance “simple”
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\FactureLigneController;
use App\Http\Controllers\Api\ReglementController;

// Finance “module” (invoices / payments)
use App\Http\Controllers\Api\Finance\InvoiceController;
use App\Http\Controllers\Api\Finance\PaymentController;

use App\Http\Controllers\Api\PediatrieController;
use App\Http\Controllers\Api\GynecologieController;
use App\Http\Controllers\Api\SmiController;
use App\Http\Controllers\Api\MaterniteController;
use App\Http\Controllers\Api\GestionMaladeController;
use App\Http\Controllers\Api\SanitaireController;
use App\Http\Controllers\Api\KinesitherapieController;
use App\Http\Controllers\Api\AruController;
use App\Http\Controllers\Api\BlocOperatoireController;

Route::prefix('v1')->group(function () {

    // ── Auth ─────────────────────────────────────────────────────────────
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me',     [AuthController::class, 'me']);
    });

    // ── Admin (/api/v1/admin/...) ───────────────────────────────────────
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:admin'])
        ->group(function () {
            Route::apiResource('users', UserManagementController::class)
                ->parameters(['users' => 'user']);
            Route::apiResource('personnels', PersonnelController::class)
                ->parameters(['personnels' => 'personnel']);
        });

    // ── Services (slug scopé) ───────────────────────────────────────────
    Route::apiResource('services', ServiceController::class)
        ->scoped(['service' => 'slug']);

    // ── Patients ────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'patients',               [PatientController::class, 'index'])->name('v1.patients.index');
        Route::post(  'patients',               [PatientController::class, 'store'])->name('v1.patients.store');
        Route::get(   'patients/{patient}',     [PatientController::class, 'show'])->name('v1.patients.show');
        Route::patch( 'patients/{patient}',     [PatientController::class, 'update'])->name('v1.patients.update');
        Route::delete('patients/{patient}',     [PatientController::class, 'destroy'])->name('v1.patients.destroy');
        Route::post(  'patients/{id}/restore',  [PatientController::class, 'restore'])->name('v1.patients.restore');
        Route::get(   'patients/{id}/history',  [PatientController::class, 'history'])->name('v1.patients.history');
    });

    // ── VISITES ─────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'visites',      [VisiteController::class,'index'])->middleware('ability:visites.read')->name('v1.visites.index');
        Route::get(   'visites/{id}', [VisiteController::class,'show'])->middleware('ability:visites.read')->name('v1.visites.show');
        Route::post(  'visites',      [VisiteController::class,'store'])->middleware('ability:visites.write')->name('v1.visites.store');
        Route::patch( 'visites/{id}', [VisiteController::class,'update'])->middleware('ability:visites.write')->name('v1.visites.update');
    });

    // ── FACTURES simples + lignes + règlements ──────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        // Factures (index, show, store)
        Route::apiResource('factures', FactureController::class)->only(['index','show','store']);

        // Lignes (nested)
        Route::apiResource('factures.lignes', FactureLigneController::class)
            ->only(['store','update','destroy'])
            ->shallow(); // /lignes/{ligne} pour update/destroy

        // Règlements (nested)
        Route::apiResource('factures.reglements', ReglementController::class)
            ->only(['store'])
            ->shallow();

        // PDF
        Route::get('factures/{facture}/pdf', [FactureController::class, 'pdf'])
            ->name('factures.pdf');
    });

    // ── Finance (Invoices / Payments) ───────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        // Invoices
        Route::get(   'finance/invoices',           [InvoiceController::class,'index']);
        Route::post(  'finance/invoices',           [InvoiceController::class,'store']);
        Route::get(   'finance/invoices/{invoice}', [InvoiceController::class,'show']);
        Route::patch( 'finance/invoices/{invoice}', [InvoiceController::class,'update']);
        Route::delete('finance/invoices/{invoice}', [InvoiceController::class,'destroy']);

        // Payments
        Route::get(   'finance/payments',           [PaymentController::class,'index']);
        Route::post(  'finance/payments',           [PaymentController::class,'store']);
    });

    // ── LABORATOIRE ─────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'laboratoire',                [LaboratoireController::class, 'index'])->name('v1.laboratoire.index');
        Route::post(  'laboratoire',                [LaboratoireController::class, 'store'])->name('v1.laboratoire.store');
        Route::get(   'laboratoire/{laboratoire}',  [LaboratoireController::class, 'show'])->name('v1.laboratoire.show');
        Route::put(   'laboratoire/{laboratoire}',  [LaboratoireController::class, 'update']);
        Route::patch( 'laboratoire/{laboratoire}',  [LaboratoireController::class, 'update'])->name('v1.laboratoire.update');
        Route::delete('laboratoire/{laboratoire}',  [LaboratoireController::class, 'destroy'])->name('v1.laboratoire.destroy');
    });

    // ── PANSEMENTS ──────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'pansements',               [PansementController::class, 'index'])->name('v1.pansements.index');
        Route::post(  'pansements',               [PansementController::class, 'store'])->name('v1.pansements.store');
        Route::get(   'pansements/{pansement}',   [PansementController::class, 'show'])->name('v1.pansements.show');
        Route::put(   'pansements/{pansement}',   [PansementController::class, 'update']);
        Route::patch( 'pansements/{pansement}',   [PansementController::class, 'update'])->name('v1.pansements.update');
        Route::delete('pansements/{pansement}',   [PansementController::class, 'destroy'])->name('v1.pansements.destroy');
    });

    // ── PÉDIATRIE ───────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'pediatrie',                [PediatrieController::class, 'index'])->name('v1.pediatrie.index');
        Route::post(  'pediatrie',                [PediatrieController::class, 'store'])->name('v1.pediatrie.store');
        Route::get(   'pediatrie/{pediatrie}',    [PediatrieController::class, 'show'])->name('v1.pediatrie.show');
        Route::patch( 'pediatrie/{pediatrie}',    [PediatrieController::class, 'update'])->name('v1.pediatrie.update');
        Route::put(   'pediatrie/{pediatrie}',    [PediatrieController::class, 'update']);
        Route::delete('pediatrie/{pediatrie}',    [PediatrieController::class, 'destroy'])->name('v1.pediatrie.destroy');
        Route::get(   'pediatrie-corbeille',      [PediatrieController::class, 'trash'])->name('v1.pediatrie.trash');
        Route::post(  'pediatrie/{id}/restore',   [PediatrieController::class, 'restore'])->name('v1.pediatrie.restore');
        Route::delete('pediatrie/{id}/force',     [PediatrieController::class, 'forceDestroy'])->name('v1.pediatrie.force');
    });

    // ── GYNÉCOLOGIE ─────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'gynecologie',               [GynecologieController::class, 'index'])->name('v1.gynecologie.index');
        Route::post(  'gynecologie',               [GynecologieController::class, 'store'])->name('v1.gynecologie.store');
        Route::get(   'gynecologie/{gynecologie}', [GynecologieController::class, 'show'])->name('v1.gynecologie.show');
        Route::patch( 'gynecologie/{gynecologie}', [GynecologieController::class, 'update'])->name('v1.gynecologie.update');
        Route::put(   'gynecologie/{gynecologie}', [GynecologieController::class, 'update']);
        Route::delete('gynecologie/{gynecologie}', [GynecologieController::class, 'destroy'])->name('v1.gynecologie.destroy');
        Route::post(  'gynecologie/{id}/restore',  [GynecologieController::class, 'restore'])->name('v1.gynecologie.restore');
        Route::delete('gynecologie/{id}/force',    [GynecologieController::class, 'forceDelete'])->name('v1.gynecologie.force');
    });

    // ── SMI ─────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'smi',             [SmiController::class, 'index'])->name('v1.smi.index');
        Route::post(  'smi',             [SmiController::class, 'store'])->name('v1.smi.store');
        Route::get(   'smi/{smi}',       [SmiController::class, 'show'])->name('v1.smi.show');
        Route::patch( 'smi/{smi}',       [SmiController::class, 'update'])->name('v1.smi.update');
        Route::put(   'smi/{smi}',       [SmiController::class, 'update']);
        Route::delete('smi/{smi}',       [SmiController::class, 'destroy'])->name('v1.smi.destroy');
        Route::get(   'smi-corbeille',   [SmiController::class, 'trash'])->name('v1.smi.trash');
        Route::post(  'smi/{id}/restore',[SmiController::class, 'restore'])->name('v1.smi.restore');
        Route::delete('smi/{id}/force',  [SmiController::class, 'forceDestroy'])->name('v1.smi.force');
    });

    // ── MATERNITÉ ───────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'maternite',               [MaterniteController::class, 'index'])->name('v1.maternite.index');
        Route::post(  'maternite',               [MaterniteController::class, 'store'])->name('v1.maternite.store');
        Route::get(   'maternite/{maternite}',   [MaterniteController::class, 'show'])->name('v1.maternite.show');
        Route::patch( 'maternite/{maternite}',   [MaterniteController::class, 'update'])->name('v1.maternite.update');
        Route::put(   'maternite/{maternite}',   [MaterniteController::class, 'update']);
        Route::delete('maternite/{maternite}',   [MaterniteController::class, 'destroy'])->name('v1.maternite.destroy');
        Route::get(   'maternite-corbeille',     [MaterniteController::class, 'trash'])->name('v1.maternite.trash');
        Route::post(  'maternite/{id}/restore',  [MaterniteController::class, 'restore'])->name('v1.maternite.restore');
        Route::delete('maternite/{id}/force',    [MaterniteController::class, 'forceDestroy'])->name('v1.maternite.force');
    });

    // ── GESTION MALADE ──────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'gestion-malade',                 [GestionMaladeController::class, 'index'])->name('v1.gestion_malade.index');
        Route::post(  'gestion-malade',                 [GestionMaladeController::class, 'store'])->name('v1.gestion_malade.store');
        Route::get(   'gestion-malade/{gestion_malade}',[GestionMaladeController::class, 'show'])->name('v1.gestion_malade.show');
        Route::patch( 'gestion-malade/{gestion_malade}',[GestionMaladeController::class, 'update'])->name('v1.gestion_malade.update');
        Route::put(   'gestion-malade/{gestion_malade}',[GestionMaladeController::class, 'update']);
        Route::delete('gestion-malade/{gestion_malade}',[GestionMaladeController::class, 'destroy'])->name('v1.gestion_malade.destroy');
        Route::get(   'gestion-malade-corbeille',       [GestionMaladeController::class, 'trash'])->name('v1.gestion_malade.trash');
        Route::post(  'gestion-malade/{id}/restore',    [GestionMaladeController::class, 'restore'])->name('v1.gestion_malade.restore');
        Route::delete('gestion-malade/{id}/force',      [GestionMaladeController::class, 'forceDestroy'])->name('v1.gestion_malade.force');
    });

    // ── SANITAIRE ───────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'sanitaire',               [SanitaireController::class, 'index'])->name('v1.sanitaire.index');
        Route::post(  'sanitaire',               [SanitaireController::class, 'store'])->name('v1.sanitaire.store');
        Route::get(   'sanitaire/{sanitaire}',   [SanitaireController::class, 'show'])->name('v1.sanitaire.show');
        Route::patch( 'sanitaire/{sanitaire}',   [SanitaireController::class, 'update'])->name('v1.sanitaire.update');
        Route::put(   'sanitaire/{sanitaire}',   [SanitaireController::class, 'update']);
        Route::delete('sanitaire/{sanitaire}',   [SanitaireController::class, 'destroy'])->name('v1.sanitaire.destroy');
        Route::get(   'sanitaire-corbeille',     [SanitaireController::class, 'trash'])->name('v1.sanitaire.trash');
        Route::post(  'sanitaire/{id}/restore',  [SanitaireController::class, 'restore'])->name('v1.sanitaire.restore');
        Route::delete('sanitaire/{id}/force',    [SanitaireController::class, 'forceDestroy'])->name('v1.sanitaire.force');
    });

    // ── KINESITHERAPIE ──────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'kinesitherapie',                 [KinesitherapieController::class, 'index'])->name('v1.kine.index');
        Route::post(  'kinesitherapie',                 [KinesitherapieController::class, 'store'])->name('v1.kine.store');
        Route::get(   'kinesitherapie/{kinesitherapie}',[KinesitherapieController::class, 'show'])->name('v1.kine.show');
        Route::patch( 'kinesitherapie/{kinesitherapie}',[KinesitherapieController::class, 'update'])->name('v1.kine.update');
        Route::put(   'kinesitherapie/{kinesitherapie}',[KinesitherapieController::class, 'update']);
        Route::delete('kinesitherapie/{kinesitherapie}',[KinesitherapieController::class, 'destroy'])->name('v1.kine.destroy');
        Route::get(   'kinesitherapie-corbeille',       [KinesitherapieController::class, 'trash'])->name('v1.kine.trash');
        Route::post(  'kinesitherapie/{id}/restore',    [KinesitherapieController::class, 'restore'])->name('v1.kine.restore');
        Route::delete('kinesitherapie/{id}/force',      [KinesitherapieController::class, 'forceDestroy'])->name('v1.kine.force');
    });

    // ── ARU ─────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'aru',               [AruController::class, 'index'])->name('v1.aru.index');
        Route::post(  'aru',               [AruController::class, 'store'])->name('v1.aru.store');
        Route::get(   'aru/{aru}',         [AruController::class, 'show'])->name('v1.aru.show');
        Route::patch( 'aru/{aru}',         [AruController::class, 'update'])->name('v1.aru.update');
        Route::put(   'aru/{aru}',         [AruController::class, 'update']);
        Route::delete('aru/{aru}',         [AruController::class, 'destroy'])->name('v1.aru.destroy');
        Route::get(   'aru-corbeille',     [AruController::class, 'trash'])->name('v1.aru.trash');
        Route::post(  'aru/{id}/restore',  [AruController::class, 'restore'])->name('v1.aru.restore');
        Route::delete('aru/{id}/force',    [AruController::class, 'forceDestroy'])->name('v1.aru.force');
    });

    // ── BLOC OPÉRATOIRE ────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'bloc-operatoire',                    [BlocOperatoireController::class, 'index'])->name('v1.bloc.index');
        Route::post(  'bloc-operatoire',                    [BlocOperatoireController::class, 'store'])->name('v1.bloc.store');
        Route::get(   'bloc-operatoire/{bloc_operatoire}',  [BlocOperatoireController::class, 'show'])->name('v1.bloc.show');
        Route::patch( 'bloc-operatoire/{bloc_operatoire}',  [BlocOperatoireController::class, 'update'])->name('v1.bloc.update');
        Route::put(   'bloc-operatoire/{bloc_operatoire}',  [BlocOperatoireController::class, 'update']);
        Route::delete('bloc-operatoire/{bloc_operatoire}',  [BlocOperatoireController::class, 'destroy'])->name('v1.bloc.destroy');
        Route::get(   'bloc-operatoire-corbeille',          [BlocOperatoireController::class, 'trash'])->name('v1.bloc.trash');
        Route::post(  'bloc-operatoire/{id}/restore',       [BlocOperatoireController::class, 'restore'])->name('v1.bloc.restore');
        Route::delete('bloc-operatoire/{id}/force',         [BlocOperatoireController::class, 'forceDestroy'])->name('v1.bloc.force');
    });

    // ── CONSULTATIONS ──────────────────────────────────────────────────
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get(   'consultations',                 [ConsultationController::class, 'index'])->name('v1.consultations.index');
        Route::post(  'consultations',                 [ConsultationController::class, 'store'])->name('v1.consultations.store');
        Route::get(   'consultations/{consultation}',  [ConsultationController::class, 'show'])->name('v1.consultations.show');
        Route::patch( 'consultations/{consultation}',  [ConsultationController::class, 'update'])->name('v1.consultations.update');
        Route::put(   'consultations/{consultation}',  [ConsultationController::class, 'update']);
        Route::delete('consultations/{consultation}',  [ConsultationController::class, 'destroy'])->name('v1.consultations.destroy');
        Route::get(   'consultations-corbeille',       [ConsultationController::class, 'trash'])->name('v1.consultations.trash');
        Route::post(  'consultations/{id}/restore',    [ConsultationController::class, 'restore'])->name('v1.consultations.restore');
        Route::delete('consultations/{id}/force',      [ConsultationController::class, 'forceDestroy'])->name('v1.consultations.force');
    });

});
