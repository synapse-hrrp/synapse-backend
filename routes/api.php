<?php

use Illuminate\Support\Facades\Route;

// ── Controllers ───────────────────────────────────────────────────────────
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\PersonnelController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\IncomingController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\ExamenController;

use App\Http\Controllers\Api\Admin\UserRoleController; // ⬅️ contrôleur utilisé pour roles/services

use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\MedecinController;
use App\Http\Controllers\Api\VisiteController;
use App\Http\Controllers\Api\LaboratoireController;
use App\Http\Controllers\Api\PansementController;

// Finance “simple”
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\FactureLigneController;
use App\Http\Controllers\Api\ReglementController;
use App\Http\Controllers\Api\FactureItemController;

// ── Controller Tarif ──────────────────────────────────────────────────────
use App\Http\Controllers\Api\TarifController;

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
use App\Http\Controllers\Api\MedecineController;
use App\Http\Controllers\Api\EchographieController;
use App\Http\Controllers\Api\BilletSortieController;
use App\Http\Controllers\Api\DeclarationNaissanceController;
use App\Http\Controllers\Api\HospitalisationController;
use App\Http\Controllers\Api\PlanningController;
use App\Http\Controllers\Api\RendezVousController;


// AJOUT INVENTAIRE LABO
use App\Http\Controllers\Api\ReagentController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ReportController;

// Ajout pharmacie
use App\Http\Controllers\Api\Pharma\DciController;
use App\Http\Controllers\Api\Pharma\ArticleController;
use App\Http\Controllers\Api\Pharma\StockController;
use App\Http\Controllers\Api\Pharma\CartController;

// Ajout Caisse
use App\Http\Controllers\Api\Caisse\CashSessionController;
use App\Http\Controllers\Api\Caisse\CashReportController;
use App\Http\Controllers\Api\Caisse\CashRegisterSessionController;
use App\Http\Controllers\Api\Caisse\CashAuditController; 
use App\Http\Controllers\Api\Admin\UserServiceController;

Route::prefix('v1')->group(function () {

    // ── Auth (/api/v1/auth) ────────────────────────────────────────────────
    Route::post('auth/login',   [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/refresh', [AuthController::class, 'refresh'])->middleware(['auth:sanctum','throttle:auth']);
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me',      [AuthController::class, 'me']);
    });

    /* =====================================================================
     *  🔧 Routes d’affectation (rôles & services) compatibles avec le front
     *  - Autorisation fine gérée DANS le contrôleur (admin|admin_caisse|roles.assign)
     *  - On ne met PAS de 'role_or_permission' ou 'can:...' ici pour éviter 403/404
     * ===================================================================== */
    Route::prefix('admin')->middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::post('/users/{user}/roles',    [UserRoleController::class, 'syncRoles'])->name('v1.admin.users.roles.sync');
        Route::post('/users/{user}/services', [UserServiceController::class, 'sync'])->name('v1.admin.users.services.sync');
    });

    // ── Admin (/api/v1/admin) ─────────────────────────────
    Route::prefix('admin')
        ->middleware(['auth:sanctum','throttle:auth','role:admin'])
        ->group(function () {
            // Users
            Route::apiResource('users', UserManagementController::class)
                ->parameters(['users' => 'user']);

            // Personnels
            Route::apiResource('personnels', PersonnelController::class)
                ->parameters(['personnels' => 'personnel']);

            // Recherche par user_id
            Route::get('personnels/by-user/{user_id}', [PersonnelController::class, 'byUser'])
                ->name('v1.personnels.by_user');

            // Endpoints avatar dédiés
            Route::post('personnels/{personnel}/avatar',  [PersonnelController::class, 'uploadAvatar'])
                ->name('v1.personnels.avatar.upload');
            Route::delete('personnels/{personnel}/avatar',[\App\Http\Controllers\Api\Admin\PersonnelController::class, 'deleteAvatar'])
                ->name('v1.personnels.avatar.delete');
        });

    // ── Medecins Lookup ─────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth','ability:consultations.view'])
        ->get('lookups/medecins', [LookupController::class, 'medecins'])
        ->name('v1.lookups.medecins');

    // ── Services (slug scopé) ────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])
        ->apiResource('services', ServiceController::class)
        ->scoped(['service' => 'slug']);

    // Enregistrement des "incoming" pour un service donné
    Route::middleware(['auth:sanctum','throttle:auth'])
        ->post('services/{service}/incoming', [IncomingController::class, 'store'])
        ->name('v1.services.incoming');

    // Options pour un personnel
    Route::middleware(['auth:sanctum','throttle:auth'])
        ->get('services/options-for-personnel/{personnel}', [ServiceController::class, 'optionsForPersonnel'])
        ->whereNumber('personnel')
        ->name('v1.services.options_for_personnel');

    // ── Patients ────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'patients',              [PatientController::class, 'index'])
            ->middleware('ability:patients.view')->name('v1.patients.index');

        Route::post(  'patients',              [PatientController::class, 'store'])
            ->middleware('ability:patients.create')->name('v1.patients.store');

        Route::get(   'patients/{patient}',    [PatientController::class, 'show'])
            ->middleware('ability:patients.view')->name('v1.patients.show');

        Route::patch( 'patients/{patient}',    [PatientController::class, 'update'])
            ->middleware('ability:patients.update')->name('v1.patients.update');

        Route::delete('patients/{patient}',    [PatientController::class, 'destroy'])
            ->middleware('ability:patients.delete')->name('v1.patients.destroy');

        Route::post(  'patients/{id}/restore', [PatientController::class, 'restore'])
            ->middleware('ability:patients.update')->name('v1.patients.restore');

        Route::get(   'patients/{id}/history', [PatientController::class, 'history'])
            ->middleware('ability:patients.view')->name('v1.patients.history');
    });

    // ── Visites ─────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(  'visites',      [VisiteController::class,'index'])
            ->middleware('ability:visites.read')->name('v1.visites.index');

        Route::get(  'visites/{id}', [VisiteController::class,'show'])
            ->middleware('ability:visites.read')->name('v1.visites.show');

        Route::post( 'visites',      [VisiteController::class,'store'])
            ->middleware('ability:visites.write')->name('v1.visites.store');

        Route::patch('visites/{id}', [VisiteController::class,'update'])
            ->middleware('ability:visites.write')->name('v1.visites.update');
    });

    // ── FACTURES simples + lignes + règlements ──────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::apiResource('factures', FactureController::class)->only(['index','show','store']);

        Route::apiResource('factures.lignes', FactureLigneController::class)
            ->only(['store','update','destroy'])
            ->shallow();

        Route::apiResource('factures.reglements', ReglementController::class)
            ->only(['store'])
            ->shallow();

        Route::get('factures/{facture}/pdf', [FactureController::class, 'pdf'])
            ->name('factures.pdf');
    });

    // ── Finance – Invoices & Payments ────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        // Invoices
        Route::get(   'finance/invoices',           [InvoiceController::class,'index'])
            ->middleware('ability:finance.invoice.view');

        Route::post(  'finance/invoices',           [InvoiceController::class,'store'])
            ->middleware('ability:finance.invoice.create');

        Route::get(   'finance/invoices/{invoice}', [InvoiceController::class,'show'])
            ->middleware('ability:finance.invoice.view');

        Route::patch( 'finance/invoices/{invoice}', [InvoiceController::class,'update'])
            ->middleware('ability:finance.invoice.create');

        Route::delete('finance/invoices/{invoice}', [InvoiceController::class,'destroy'])
            ->middleware('ability:finance.invoice.create');

        // Payments
        Route::get(   'finance/payments',           [PaymentController::class,'index'])
            ->middleware('ability:finance.payment.create');

        Route::post(  'finance/payments',           [PaymentController::class,'store'])
            ->middleware('ability:finance.payment.create');
    });

    // ── Laboratoire ───────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'laboratoire',               [LaboratoireController::class, 'index'])
            ->middleware('ability:labo.view')->name('v1.laboratoire.index');

        Route::post(  'laboratoire',               [LaboratoireController::class, 'store'])
            ->middleware('ability:labo.request.create')->name('v1.laboratoire.store');

        Route::get(   'laboratoire/{laboratoire}', [LaboratoireController::class, 'show'])
            ->middleware('ability:labo.view')->name('v1.laboratoire.show');

        Route::put(   'laboratoire/{laboratoire}', [LaboratoireController::class, 'update'])
            ->middleware('ability:labo.result.write');

        Route::patch( 'laboratoire/{laboratoire}', [LaboratoireController::class, 'update'])
            ->middleware('ability:labo.result.write')->name('v1.laboratoire.update');

        Route::delete('laboratoire/{laboratoire}', [LaboratoireController::class, 'destroy'])
            ->middleware('ability:labo.result.write')->name('v1.laboratoire.destroy');
    });

    // ── Pansements ────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'pansements',             [PansementController::class, 'index'])
            ->middleware('ability:pansement.view')->name('v1.pansements.index');

        Route::post(  'pansements',             [PansementController::class, 'store'])
            ->middleware('ability:pansement.create')->name('v1.pansements.store');

        Route::get(   'pansements/{pansement}', [PansementController::class, 'show'])
            ->middleware('ability:pansement.view')->name('v1.pansements.show');

        Route::put(   'pansements/{pansement}', [PansementController::class, 'update'])
            ->middleware('ability:pansement.update');

        Route::patch( 'pansements/{pansement}', [PansementController::class, 'update'])
            ->middleware('ability:pansement.update')->name('v1.pansements.update');

        Route::delete('pansements/{pansement}', [PansementController::class, 'destroy'])
            ->middleware('ability:pansement.delete')->name('v1.pansements.destroy');
    });

    // ── Pédiatrie + corbeille ─────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'pediatrie',              [PediatrieController::class, 'index'])
            ->middleware('ability:pediatrie.view')->name('v1.pediatrie.index');

        Route::post(  'pediatrie',              [PediatrieController::class, 'store'])
            ->middleware('ability:pediatrie.create')->name('v1.pediatrie.store');

        Route::get(   'pediatrie/{pediatrie}',  [PediatrieController::class, 'show'])
            ->middleware('ability:pediatrie.view')->name('v1.pediatrie.show');

        Route::patch( 'pediatrie/{pediatrie}',  [PediatrieController::class, 'update'])
            ->middleware('ability:pediatrie.update')->name('v1.pediatrie.update');

        Route::put(   'pediatrie/{pediatrie}',  [PediatrieController::class, 'update'])
            ->middleware('ability:pediatrie.update');

        Route::delete('pediatrie/{pediatrie}',  [PediatrieController::class, 'destroy'])
            ->middleware('ability:pediatrie.delete')->name('v1.pediatrie.destroy');

        Route::get(   'pediatrie-corbeille',    [PediatrieController::class, 'trash'])
            ->middleware('ability:pediatrie.view')->name('v1.pediatrie.trash');

        Route::post(  'pediatrie/{id}/restore', [PediatrieController::class, 'restore'])
            ->middleware('ability:pediatrie.update')->name('v1.pediatrie.restore');

        Route::delete('pediatrie/{id}/force',   [PediatrieController::class, 'forceDestroy'])
            ->middleware('ability:pediatrie.delete')->name('v1.pediatrie.force');
    });

    // ── Gynécologie ────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'gynecologie',               [GynecologieController::class, 'index'])
            ->middleware('ability:gynecologie.view')->name('v1.gynecologie.index');

        Route::post(  'gynecologie',               [GynecologieController::class, 'store'])
            ->middleware('ability:gynecologie.create')->name('v1.gynecologie.store');

        Route::get(   'gynecologie/{gynecologie}', [GynecologieController::class, 'show'])
            ->middleware('ability:gynecologie.view')->name('v1.gynecologie.show');

        Route::patch( 'gynecologie/{gynecologie}', [GynecologieController::class, 'update'])
            ->middleware('ability:gynecologie.update')->name('v1.gynecologie.update');

        Route::put(   'gynecologie/{gynecologie}', [GynecologieController::class, 'update'])
            ->middleware('ability:gynecologie.update');

        Route::delete('gynecologie/{gynecologie}', [GynecologieController::class, 'destroy'])
            ->middleware('ability:gynecologie.delete')->name('v1.gynecologie.destroy');

        Route::post(  'gynecologie/{id}/restore',  [GynecologieController::class, 'restore'])
            ->middleware('ability:gynecologie.update')->name('v1.gynecologie.restore');

        Route::delete('gynecologie/{id}/force',    [GynecologieController::class, 'forceDelete'])
            ->middleware('ability:gynecologie.delete')->name('v1.gynecologie.force');
    });

    // ── SMI ────────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'smi',           [SmiController::class, 'index'])
            ->middleware('ability:smi.view')->name('v1.smi.index');

        Route::post(  'smi',           [SmiController::class, 'store'])
            ->middleware('ability:smi.create')->name('v1.smi.store');

        Route::get(   'smi/{smi}',     [SmiController::class, 'show'])
            ->middleware('ability:smi.view')->name('v1.smi.show');

        Route::patch( 'smi/{smi}',     [SmiController::class, 'update'])
            ->middleware('ability:smi.update')->name('v1.smi.update');

        Route::put(   'smi/{smi}',     [SmiController::class, 'update'])
            ->middleware('ability:smi.update');

        Route::delete('smi/{smi}',     [SmiController::class, 'destroy'])
            ->middleware('ability:smi.delete')->name('v1.smi.destroy');

        Route::get(   'smi-corbeille', [SmiController::class, 'trash'])
            ->middleware('ability:smi.view')->name('v1.smi.trash');

        Route::post(  'smi/{id}/restore', [SmiController::class, 'restore'])
            ->middleware('ability:smi.update')->name('v1.smi.restore');

        Route::delete('smi/{id}/force',   [SmiController::class, 'forceDestroy'])
            ->middleware('ability:smi.delete')->name('v1.smi.force');
    });

    // ── Maternité ──────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'maternite',               [MaterniteController::class, 'index'])
            ->middleware('ability:maternite.view')->name('v1.maternite.index');

        Route::post(  'maternite',               [MaterniteController::class, 'store'])
            ->middleware('ability:maternite.create')->name('v1.maternite.store');

        Route::get(   'maternite/{maternite}',   [MaterniteController::class, 'show'])
            ->middleware('ability:maternite.view')->name('v1.maternite.show');

        Route::patch( 'maternite/{maternite}',   [MaterniteController::class, 'update'])
            ->middleware('ability:maternite.update')->name('v1.maternite.update');

        Route::put(   'maternite/{maternite}',   [MaterniteController::class, 'update'])
            ->middleware('ability:maternite.update');

        Route::delete('maternite/{maternite}',   [MaterniteController::class, 'destroy'])
            ->middleware('ability:maternite.delete')->name('v1.maternite.destroy');

        Route::get(   'maternite-corbeille',     [MaterniteController::class, 'trash'])
            ->middleware('ability:maternite.view')->name('v1.maternite.trash');

        Route::post(  'maternite/{id}/restore',  [MaterniteController::class, 'restore'])
            ->middleware('ability:maternite.update')->name('v1.maternite.restore');

        Route::delete('maternite/{id}/force',    [MaterniteController::class, 'forceDestroy'])
            ->middleware('ability:maternite.delete')->name('v1.maternite.force');
    });

    // ── Gestion malade ─────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'gestion-malade',                  [GestionMaladeController::class, 'index'])
            ->middleware('ability:gestion-malade.view')->name('v1.gestion_malade.index');

        Route::post(  'gestion-malade',                  [GestionMaladeController::class, 'store'])
            ->middleware('ability:gestion-malade.create')->name('v1.gestion_malade.store');

        Route::get(   'gestion-malade/{gestion_malade}', [GestionMaladeController::class, 'show'])
            ->middleware('ability:gestion-malade.view')->name('v1.gestion_malade.show');

        Route::patch( 'gestion-malade/{gestion_malade}', [GestionMaladeController::class, 'update'])
            ->middleware('ability:gestion-malade.update')->name('v1.gestion_malade.update');

        Route::put(   'gestion-malade/{gestion_malade}', [GestionMaladeController::class, 'update'])
            ->middleware('ability:gestion-malade.update');

        Route::delete('gestion-malade/{gestion_malade}', [GestionMaladeController::class, 'destroy'])
            ->middleware('ability:gestion-malade.delete')->name('v1.gestion_malade.destroy');

        Route::get(   'gestion-malade-corbeille',        [GestionMaladeController::class, 'trash'])
            ->middleware('ability:gestion-malade.view')->name('v1.gestion_malade.trash');

        Route::post(  'gestion-malade/{id}/restore',     [GestionMaladeController::class, 'restore'])
            ->middleware('ability:gestion-malade.update')->name('v1.gestion_malade.restore');

        Route::delete('gestion-malade/{id}/force',       [GestionMaladeController::class, 'forceDestroy'])
            ->middleware('ability:gestion-malade.delete')->name('v1.gestion_malade.force');
    });

    // ── Sanitaire ──────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'sanitaire',               [SanitaireController::class, 'index'])
            ->middleware('ability:sanitaire.view')->name('v1.sanitaire.index');

        Route::post(  'sanitaire',               [SanitaireController::class, 'store'])
            ->middleware('ability:sanitaire.create')->name('v1.sanitaire.store');

        Route::get(   'sanitaire/{sanitaire}',   [SanitaireController::class, 'show'])
            ->middleware('ability:sanitaire.view')->name('v1.sanitaire.show');

        Route::patch( 'sanitaire/{sanitaire}',   [SanitaireController::class, 'update'])
            ->middleware('ability:sanitaire.update')->name('v1.sanitaire.update');

        Route::put(   'sanitaire/{sanitaire}',   [SanitaireController::class, 'update'])
            ->middleware('ability:sanitaire.update');

        Route::delete('sanitaire/{sanitaire}',   [SanitaireController::class, 'destroy'])
            ->middleware('ability:sanitaire.delete')->name('v1.sanitaire.destroy');

        Route::get(   'sanitaire-corbeille',     [SanitaireController::class, 'trash'])
            ->middleware('ability:sanitaire.view')->name('v1.sanitaire.trash');

        Route::post(  'sanitaire/{id}/restore',  [SanitaireController::class, 'restore'])
            ->middleware('ability:sanitaire.update')->name('v1.sanitaire.restore');

        Route::delete('sanitaire/{id}/force',    [SanitaireController::class, 'forceDestroy'])
            ->middleware('ability:sanitaire.delete')->name('v1.sanitaire.force');
    });

    // ── Kinésithérapie ─────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'kinesitherapie',                  [KinesitherapieController::class, 'index'])
            ->middleware('ability:kinesitherapie.view')->name('v1.kine.index');

        Route::post(  'kinesitherapie',                  [KinesitherapieController::class, 'store'])
            ->middleware('ability:kinesitherapie.create')->name('v1.kine.store');

        Route::get(   'kinesitherapie/{kinesitherapie}', [KinesitherapieController::class, 'show'])
            ->middleware('ability:kinesitherapie.view')->name('v1.kine.show');

        Route::patch( 'kinesitherapie/{kinesitherapie}', [KinesitherapieController::class, 'update'])
            ->middleware('ability:kinesitherapie.update')->name('v1.kine.update');

        Route::put(   'kinesitherapie/{kinesitherapie}', [KinesitherapieController::class, 'update'])
            ->middleware('ability:kinesitherapie.update');

        Route::delete('kinesitherapie/{kinesitherapie}', [KinesitherapieController::class, 'destroy'])
            ->middleware('ability:kinesitherapie.delete')->name('v1.kine.destroy');

        Route::get(   'kinesitherapie-corbeille',        [KinesitherapieController::class, 'trash'])
            ->middleware('ability:kinesitherapie.view')->name('v1.kine.trash');

        Route::post(  'kinesitherapie/{id}/restore',     [KinesitherapieController::class, 'restore'])
            ->middleware('ability:kinesitherapie.update')->name('v1.kine.restore');

        Route::delete('kinesitherapie/{id}/force',       [KinesitherapieController::class, 'forceDestroy'])
            ->middleware('ability:kinesitherapie.delete')->name('v1.kine.force');
    });

    // ── ARU ────────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'aru',           [AruController::class, 'index'])
            ->middleware('ability:aru.view')->name('v1.aru.index');

        Route::post(  'aru',           [AruController::class, 'store'])
            ->middleware('ability:aru.create')->name('v1.aru.store');

        Route::get(   'aru/{aru}',     [AruController::class, 'show'])
            ->middleware('ability:aru.view')->name('v1.aru.show');

        Route::patch( 'aru/{aru}',     [AruController::class, 'update'])
            ->middleware('ability:aru.update')->name('v1.aru.update');

        Route::put(   'aru/{aru}',     [AruController::class, 'update'])
            ->middleware('ability:aru.update');

        Route::delete('aru/{aru}',     [AruController::class, 'destroy'])
            ->middleware('ability:aru.delete')->name('v1.aru.destroy');

        Route::get(   'aru-corbeille', [AruController::class, 'trash'])
            ->middleware('ability:aru.view')->name('v1.aru.trash');

        Route::post(  'aru/{id}/restore', [AruController::class, 'restore'])
            ->middleware('ability:aru.update')->name('v1.aru.restore');

        Route::delete('aru/{id}/force',   [AruController::class, 'forceDestroy'])
            ->middleware('ability:aru.delete')->name('v1.aru.force');
    });

    // ── Bloc opératoire ───────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'bloc-operatoire',                    [BlocOperatoireController::class, 'index'])
            ->middleware('ability:bloc-operatoire.view')->name('v1.bloc.index');

        Route::post(  'bloc-operatoire',                    [BlocOperatoireController::class, 'store'])
            ->middleware('ability:bloc-operatoire.create')->name('v1.bloc.store');

        Route::get(   'bloc-operatoire/{bloc_operatoire}',  [BlocOperatoireController::class, 'show'])
            ->middleware('ability:bloc-operatoire.view')->name('v1.bloc.show');

        Route::patch( 'bloc-operatoire/{bloc_operatoire}',  [BlocOperatoireController::class, 'update'])
            ->middleware('ability:bloc-operatoire.update')->name('v1.bloc.update');

        Route::put(   'bloc-operatoire/{bloc_operatoire}',  [BlocOperatoireController::class, 'update'])
            ->middleware('ability:bloc-operatoire.update');

        Route::delete('bloc-operatoire/{bloc_operatoire}',  [BlocOperatoireController::class, 'destroy'])
            ->middleware('ability:bloc-operatoire.delete')->name('v1.bloc.destroy');

        Route::get(   'bloc-operatoire-corbeille',          [BlocOperatoireController::class, 'trash'])
            ->middleware('ability:bloc-operatoire.view')->name('v1.bloc.trash');

        Route::post(  'bloc-operatoire/{id}/restore',       [BlocOperatoireController::class, 'restore'])
            ->middleware('ability:bloc-operatoire.update')->name('v1.bloc.restore');

        Route::delete('bloc-operatoire/{id}/force',         [BlocOperatoireController::class, 'forceDestroy'])
            ->middleware('ability:bloc-operatoire.delete')->name('v1.bloc.force');
    });

    // ── Consultations ──────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'consultations',                 [ConsultationController::class, 'index'])
            ->middleware('ability:consultations.view')->name('v1.consultations.index');

        Route::post(  'consultations',                 [ConsultationController::class, 'store'])
            ->middleware('ability:consultations.create')->name('v1.consultations.store');

        Route::get(   'consultations/{consultation}',  [ConsultationController::class, 'show'])
            ->middleware('ability:consultations.view')->name('v1.consultations.show');

        Route::patch( 'consultations/{consultation}',  [ConsultationController::class, 'update'])
            ->middleware('ability:consultations.update')->name('v1.consultations.update');

        Route::put(   'consultations/{consultation}',  [ConsultationController::class, 'update'])
            ->middleware('ability:consultations.update');

        Route::delete('consultations/{consultation}',  [ConsultationController::class, 'destroy'])
            ->middleware('ability:consultations.delete')->name('v1.consultations.destroy');

        Route::get(   'consultations-corbeille',       [ConsultationController::class, 'trash'])
            ->middleware('ability:consultations.view')->name('v1.consultations.trash');

        Route::post(  'consultations/{id}/restore',    [ConsultationController::class, 'restore'])
            ->middleware('ability:consultations.update')->name('v1.consultations.restore');

        Route::delete('consultations/{id}/force',      [ConsultationController::class, 'forceDestroy'])
            ->middleware('ability:consultations.delete')->name('v1.consultations.force');
    });

    // ── Médecine ───────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'medecines',               [MedecineController::class, 'index'])
            ->middleware('ability:medecine.view')->name('v1.medecines.index');

        Route::post(  'medecines',               [MedecineController::class, 'store'])
            ->middleware('ability:medecine.create')->name('v1.medecines.store');

        Route::get(   'medecines/{medecine}',    [MedecineController::class, 'show'])
            ->middleware('ability:medecine.view')->name('v1.medecines.show');

        Route::patch( 'medecines/{medecine}',    [MedecineController::class, 'update'])
            ->middleware('ability:medecine.update')->name('v1.medecines.update');

        Route::put(   'medecines/{medecine}',    [MedecineController::class, 'update'])
            ->middleware('ability:medecine.update');

        Route::delete('medecines/{medecine}',    [MedecineController::class, 'destroy'])
            ->middleware('ability:medecine.delete')->name('v1.medecines.destroy');
    });

    // ── Examens ────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'examens',            [ExamenController::class, 'index'])
            ->middleware('ability:examen.view')->name('v1.examen.index');

        Route::post(  'examens',            [ExamenController::class, 'store'])
            ->middleware('ability:examen.create')->name('v1.examen.store');

        Route::get(   'examens/{examen}',   [ExamenController::class, 'show'])
            ->middleware('ability:examen.view')->name('v1.examen.show');

        Route::patch( 'examens/{examen}',   [ExamenController::class, 'update'])
            ->middleware('ability:examen.update')->name('v1.examen.update');

        Route::put(   'examens/{examen}',   [ExamenController::class, 'update'])
            ->middleware('ability:examen.update');

        Route::delete('examens/{examen}',   [ExamenController::class, 'destroy'])
            ->middleware('ability:examen.delete')->name('v1.examen.destroy');

        // Créer un examen "depuis un service"
        Route::post('services/{service}/examens', [ExamenController::class, 'storeForService'])
            ->middleware('ability:examens.create')->name('v1.services.examens.store');
    });

    // ── Inventaire Labo – Réactifs & Lots (/api/v1/inventory/…) ───────────
    Route::prefix('inventory')
        ->middleware(['auth:sanctum', 'throttle:auth'])
        ->group(function () {

            // REAGENTS
            Route::post('reagents', [ReagentController::class, 'store'])
                ->middleware('ability:inventory.write')
                ->name('v1.inventory.reagents.store');

            Route::get('reagents', [ReagentController::class, 'index'])
                ->middleware('ability:inventory.view')
                ->name('v1.inventory.reagents.index');

            Route::get('reagents/{reagent}', [ReagentController::class, 'show'])
                ->middleware('ability:inventory.view')
                ->whereNumber('reagent')
                ->name('v1.inventory.reagents.show');

            Route::match(['put', 'patch'], 'reagents/{reagent}', [ReagentController::class, 'update'])
                ->middleware('ability:inventory.write')
                ->whereNumber('reagent')
                ->name('v1.inventory.reagents.update');

            Route::delete('reagents/{reagent}', [ReagentController::class, 'destroy'])
                ->middleware('ability:inventory.admin')
                ->whereNumber('reagent')
                ->name('v1.inventory.reagents.destroy');

            // LOTS
            Route::post('reagents/{reagent}/lots', [LotController::class, 'store'])
                ->middleware('ability:inventory.write')
                ->whereNumber('reagent')
                ->name('v1.inventory.reagents.lots.store');

            Route::get('reagents/{reagent}/lots', [LotController::class, 'index'])
                ->middleware('ability:inventory.view')
                ->whereNumber('reagent')
                ->name('v1.inventory.reagents.lots.index');

            // Mouvements
            Route::post('reagents/{reagent}/consume-fefo', [StockMovementController::class, 'consumeFefo'])
                ->middleware('ability:inventory.write')
                ->whereNumber('reagent')
                ->name('v1.inventory.reagents.consume_fefo');

            Route::post('reagents/{reagent}/transfer', [StockMovementController::class, 'transfer'])
                ->middleware('ability:inventory.write')
                ->whereNumber('reagent')
                ->name('v1.inventory.reagents.transfer');

            Route::get('reagents/{reagent}/stock', [ReagentController::class, 'stock'])
                ->middleware('ability:inventory.view')
                ->whereNumber('reagent')
                ->name('v1.inventory.reagents.stock');

            Route::get('reagents/{reagent}/movements', [StockMovementController::class, 'index'])
                ->middleware('ability:inventory.view')
                ->whereNumber('reagent')
                ->name('v1.inventory.reagents.movements');

            // Actions sur lot
            Route::post('reagent-lots/{lot}/quarantine', [LotController::class, 'quarantine'])
                ->middleware('ability:inventory.admin')
                ->whereNumber('lot')
                ->name('v1.inventory.lots.quarantine');

            Route::post('reagent-lots/{lot}/dispose', [LotController::class, 'dispose'])
                ->middleware('ability:inventory.admin')
                ->whereNumber('lot')
                ->name('v1.inventory.lots.dispose');

            // LOCATIONS
            Route::get('locations', [LocationController::class, 'index'])
                ->middleware('ability:inventory.view')
                ->name('v1.inventory.locations.index');

            Route::post('locations', [LocationController::class, 'store'])
                ->middleware('ability:inventory.write')
                ->name('v1.inventory.locations.store');

            Route::get('locations/{location}', [LocationController::class, 'show'])
                ->middleware('ability:inventory.view')
                ->whereNumber('location')
                ->name('v1.inventory.locations.show');

            Route::match(['put','patch'], 'locations/{location}', [LocationController::class, 'update'])
                ->middleware('ability:inventory.write')
                ->whereNumber('location')
                ->name('v1.inventory.locations.update');

            Route::delete('locations/{location}', [LocationController::class, 'destroy'])
                ->middleware('ability:inventory.admin')
                ->whereNumber('location')
                ->name('v1.inventory.locations.destroy');

            // REPORTS
            Route::get('reports/reorders', [ReportController::class, 'reorders'])
                ->middleware('ability:inventory.view')
                ->name('v1.inventory.reports.reorders');

            Route::get('reports/expiries', [ReportController::class, 'expiries'])
                ->middleware('ability:inventory.view')
                ->name('v1.inventory.reports.expiries');
        });

    // ── Caisse centrale : Facture Items ────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::post('facture-items', [FactureItemController::class, 'store'])
            ->middleware('ability:cashier.item.create')
            ->name('v1.facture_items.store');

        Route::patch('facture-items/{facture_item}', [FactureItemController::class, 'update'])
            ->middleware('ability:cashier.item.update')
            ->name('v1.facture_items.update');

        Route::delete('facture-items/{facture_item}', [FactureItemController::class, 'destroy'])
            ->middleware('ability:cashier.item.delete')
            ->name('v1.facture_items.destroy');
    });

    // ── Tarifs ─────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {

        Route::get('tarifs', [TarifController::class, 'index'])
            ->name('v1.tarifs.index');

        Route::get('tarifs/actifs', [TarifController::class, 'actifs'])
            ->name('v1.tarifs.actifs');

        Route::get('tarifs/by-code/{code}', [TarifController::class, 'byCode'])
            ->name('v1.tarifs.by_code');

        Route::get('tarifs/{tarif}', [TarifController::class, 'show'])
            ->whereUuid('tarif')
            ->name('v1.tarifs.show');

        Route::post('tarifs', [TarifController::class, 'store'])
            ->name('v1.tarifs.store');

        Route::match(['put','patch'], 'tarifs/{tarif}', [TarifController::class, 'update'])
            ->whereUuid('tarif')
            ->name('v1.tarifs.update');

        Route::delete('tarifs/{tarif}', [TarifController::class, 'destroy'])
            ->whereUuid('tarif')
            ->name('v1.tarifs.destroy');

        Route::patch('tarifs/{tarif}/toggle', [TarifController::class, 'toggle'])
            ->whereUuid('tarif')
            ->name('v1.tarifs.toggle');
    });

    // ── Médecins ───────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'medecins',             [MedecinController::class, 'index'])
            ->middleware('ability:medecins.view')->name('v1.medecins.index');

        Route::post(  'medecins',             [MedecinController::class, 'store'])
            ->middleware('ability:medecins.create')->name('v1.medecins.store');

        Route::get(   'medecins/{medecin}',   [MedecinController::class, 'show'])
            ->middleware('ability:medecins.view')->name('v1.medecins.show');

        Route::patch( 'medecins/{medecin}',   [MedecinController::class, 'update'])
            ->middleware('ability:medecins.update')->name('v1.medecins.update');

        Route::put(   'medecins/{medecin}',   [MedecinController::class, 'update'])
            ->middleware('ability:medecins.update');

        Route::delete('medecins/{medecin}',   [MedecinController::class, 'destroy'])
            ->middleware('ability:medecins.delete')->name('v1.medecins.destroy');

        Route::get('medecins-corbeille',   [MedecinController::class, 'trash'])
            ->middleware('ability:medecins.view')->name('v1.medecins.trash');

        Route::post('medecins/{id}/restore',[MedecinController::class, 'restore'])
            ->middleware('ability:medecins.update')->whereNumber('id')->name('v1.medecins.restore');

        Route::delete('medecins/{id}/force',  [MedecinController::class, 'forceDestroy'])
            ->middleware('ability:medecins.delete')->whereNumber('id')->name('v1.medecins.force');

        Route::get('medecins/by-personnel/{personnel}', [MedecinController::class, 'byPersonnel'])
            ->middleware('ability:medecins.view')->whereNumber('personnel')->name('v1.medecins.by_personnel');

        Route::get('me/medecin', [MedecinController::class, 'me'])
            ->middleware('ability:medecins.view')->name('v1.me.medecin');
    });

    // ── Echographies ───────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'echographies',               [EchographieController::class, 'index'])
            ->middleware('ability:echographies.view')->name('v1.echographies.index');

        Route::post(  'echographies',               [EchographieController::class, 'store'])
            ->middleware('ability:echographies.create')->name('v1.echographies.store');

        Route::get(   'echographies/{echographie}', [EchographieController::class, 'show'])
            ->middleware('ability:echographies.view')->name('v1.echographies.show');

        Route::patch( 'echographies/{echographie}', [EchographieController::class, 'update'])
            ->middleware('ability:echographies.update')->name('v1.echographies.update');

        Route::put(   'echographies/{echographie}', [EchographieController::class, 'update'])
            ->middleware('ability:echographies.update');

        Route::delete('echographies/{echographie}', [EchographieController::class, 'destroy'])
            ->middleware('ability:echographies.delete')->name('v1.echographies.destroy');

        Route::post(  'echographies/{id}/restore', [EchographieController::class, 'restore'])
            ->middleware('ability:echographies.update')->name('v1.echographies.restore');

        Route::post('services/{service}/echographies', [EchographieController::class, 'storeForService'])
            ->middleware('ability:echographies.create')->name('v1.services.echographies.store');
    });

    // ── Billets de sortie ─────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'billets-sortie',                 [BilletSortieController::class, 'index'])
            ->middleware('ability:billets_sortie.view')->name('v1.billets_sortie.index');

        Route::post(  'billets-sortie',                 [BilletSortieController::class, 'store'])
            ->middleware('ability:billets_sortie.create')->name('v1.billets_sortie.store');

        Route::get(   'billets-sortie/{billet}',        [BilletSortieController::class, 'show'])
            ->middleware('ability:billets_sortie.view')->name('v1.billets_sortie.show');

        Route::patch( 'billets-sortie/{billet}',        [BilletSortieController::class, 'update'])
            ->middleware('ability:billets_sortie.update')->name('v1.billets_sortie.update');

        Route::put(   'billets-sortie/{billet}',        [BilletSortieController::class, 'update'])
            ->middleware('ability:billets_sortie.update');

        Route::delete('billets-sortie/{billet}',        [BilletSortieController::class, 'destroy'])
            ->middleware('ability:billets_sortie.delete')->name('v1.billets_sortie.destroy');

        Route::post(  'billets-sortie/{id}/restore',    [BilletSortieController::class, 'restore'])
            ->middleware('ability:billets_sortie.update')->name('v1.billets_sortie.restore');

        Route::post('services/{service}/billets-sortie',[BilletSortieController::class, 'storeForService'])
            ->middleware('ability:billets_sortie.create')->name('v1.services.billets_sortie.store');
    });

    // ── Déclarations de naissance ─────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'declarations-naissance',                   [DeclarationNaissanceController::class, 'index'])
            ->middleware('ability:declarations_naissance.view')->name('v1.declarations_naissance.index');

        Route::post(  'declarations-naissance',                   [DeclarationNaissanceController::class, 'store'])
            ->middleware('ability:declarations_naissance.create')->name('v1.declarations_naissance.store');

        Route::get(   'declarations-naissance/{declaration}',     [DeclarationNaissanceController::class, 'show'])
            ->middleware('ability:declarations_naissance.view')->name('v1.declarations_naissance.show');

        Route::patch( 'declarations-naissance/{declaration}',     [DeclarationNaissanceController::class, 'update'])
            ->middleware('ability:declarations_naissance.update')->name('v1.declarations_naissance.update');

        Route::put(   'declarations-naissance/{declaration}',     [DeclarationNaissanceController::class, 'update'])
            ->middleware('ability:declarations_naissance.update');

        Route::delete('declarations-naissance/{declaration}',     [DeclarationNaissanceController::class, 'destroy'])
            ->middleware('ability:declarations_naissance.delete')->name('v1.declarations_naissance.destroy');

        Route::post(  'declarations-naissance/{id}/restore',      [DeclarationNaissanceController::class, 'restore'])
            ->middleware('ability:declarations_naissance.update')->name('v1.declarations_naissance.restore');

        Route::post('services/{service}/declarations-naissance',  [DeclarationNaissanceController::class, 'storeForService'])
            ->middleware('ability:declarations_naissance.create')->name('v1.services.declarations_naissance.store');
    });

    // ── Hospitalisations ──────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::get(   'hospitalisations',                    [HospitalisationController::class, 'index'])
            ->middleware('ability:hospitalisations.view')->name('v1.hospitalisations.index');

        Route::post(  'hospitalisations',                    [HospitalisationController::class, 'store'])
            ->middleware('ability:hospitalisations.create')->name('v1.hospitalisations.store');

        Route::get(   'hospitalisations/{hospitalisation}',  [HospitalisationController::class, 'show'])
            ->middleware('ability:hospitalisations.view')->name('v1.hospitalisations.show');

        Route::patch( 'hospitalisations/{hospitalisation}',  [HospitalisationController::class, 'update'])
            ->middleware('ability:hospitalisations.update')->name('v1.hospitalisations.update');

        Route::put(   'hospitalisations/{hospitalisation}',  [HospitalisationController::class, 'update'])
            ->middleware('ability:hospitalisations.update');

        Route::delete('hospitalisations/{hospitalisation}',  [HospitalisationController::class, 'destroy'])
            ->middleware('ability:hospitalisations.delete')->name('v1.hospitalisations.destroy');

        Route::post(  'hospitalisations/{id}/restore',       [HospitalisationController::class, 'restore'])
            ->middleware('ability:hospitalisations.update')->name('v1.hospitalisations.restore');

        Route::post('services/{service}/hospitalisations',   [HospitalisationController::class, 'storeForService'])
            ->middleware('ability:hospitalisations.create')->name('v1.services.hospitalisations.store');
    });

    // ── PHARMA ────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','throttle:auth'])
        ->prefix('pharma')
        ->group(function () {

            // DCI
            Route::get('dcis/options', [DciController::class,'options']);

            Route::apiResource('dcis', DciController::class)
                ->only(['index','show']);

            Route::apiResource('dcis', DciController::class)
                ->only(['store','update','destroy']);

            // Articles
            Route::get('articles/options', [ArticleController::class,'options']);

            Route::apiResource('articles', ArticleController::class)
                ->parameters(['articles' => 'article'])
                ->only(['index','show']);

            Route::apiResource('articles', ArticleController::class)
                ->parameters(['articles' => 'article'])
                ->only(['store','update','destroy']);

            Route::get('dcis/{dci}/articles', [ArticleController::class, 'byDci']);

            Route::get('substitutes', [ArticleController::class, 'substitutes']);

            Route::post('articles/{article}/image', [ArticleController::class, 'updateImage']);

            Route::get('articles/{article}/equivalents', [\App\Http\Controllers\Api\Pharma\ArticleController::class, 'equivalents']);

            // Stock
            Route::post('stock/in',     [StockController::class,'in']);
            Route::post('stock/out',    [StockController::class,'out']);
            Route::post('stock/adjust', [StockController::class,'adjust']);

            Route::get('stock/movements',   [StockController::class,'movements']);
            Route::get('stock/summary',     [StockController::class,'summary']);
            Route::get('stock/top-sellers', [StockController::class,'topSellers']);
            Route::get('stock/oldest-lots', [StockController::class,'oldestLots']);
            Route::get('stock/alerts',      [StockController::class,'alerts']);
            Route::post('stock/thresholds', [StockController::class,'setThresholds']);

            Route::get('lots', [StockController::class, 'lots']);

            // Panier
            Route::post('carts', [CartController::class,'store']);
            Route::get('carts/{cart}', [CartController::class,'show']);
            Route::post('carts/{cart}/lines', [CartController::class,'addLine']);
            Route::patch('carts/{cart}/lines/{line}', [CartController::class,'updateLine']);
            Route::delete('carts/{cart}/lines/{line}', [CartController::class,'removeLine']);
            Route::post('carts/{cart}/checkout', [CartController::class,'checkout']);
        });

    // ────────────────────────────────────────────────
    // 🌍 Routes API - Caisse  (abilities)
    // ────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','abilities:caisse.access'])
        ->prefix('caisse')
        ->group(function () {

            // Sessions de caisse
            Route::post('/sessions/open',  [CashSessionController::class, 'open'])
                ->middleware('abilities:caisse.session.manage')
                ->name('caisse.sessions.open');

            Route::post('/sessions/close', [CashSessionController::class, 'close'])
                ->middleware('abilities:caisse.session.manage')
                ->name('caisse.sessions.close');

            Route::get('/sessions/me',     [CashSessionController::class, 'current'])
                ->middleware('abilities:caisse.session.view')
                ->name('caisse.sessions.current');

            Route::get('/sessions/current', [CashSessionController::class, 'current'])
                ->middleware('abilities:caisse.session.view')
                ->name('caisse.sessions.current.alias');

            // Paiements & rapports
            Route::get('/payments', [CashReportController::class, 'payments'])
                ->middleware('abilities:caisse.report.view')
                ->name('caisse.payments.index');

            Route::get('/rapport', [CashReportController::class, 'summary'])
                ->middleware('abilities:caisse.report.view')
                ->name('caisse.rapport.summary');

            Route::get('/top/services', [\App\Http\Controllers\Api\Caisse\CashTopController::class, 'topServices'])
                ->middleware('abilities:caisse.report.view')
                ->name('caisse.top.services');

            Route::get('/top/cashiers', [\App\Http\Controllers\Api\Caisse\CashTopController::class, 'topCashiers'])
                ->middleware('abilities:caisse.report.view')
                ->name('caisse.top.cashiers');

            Route::get('/top/overview', [\App\Http\Controllers\Api\Caisse\CashTopController::class, 'overview'])
                ->middleware('abilities:caisse.report.view')
                ->name('caisse.top.overview');

            Route::get('/z-report', [\App\Http\Controllers\Api\Caisse\CashZReportController::class, 'json'])
                ->middleware('abilities:caisse.report.view')
                ->name('caisse.zreport.json');

            Route::get('/z-report/pdf', [\App\Http\Controllers\Api\Caisse\CashZReportController::class, 'pdf'])
                ->middleware('abilities:caisse.report.view')
                ->name('caisse.zreport.pdf');

            Route::get('/audit', [CashAuditController::class, 'index'])
                ->middleware('abilities:caisse.admin.view')
                ->name('caisse.audit.index');

            // KPIs session ouverte
            Route::get('/sessions/summary', [\App\Http\Controllers\Api\Caisse\CashSessionController::class, 'summary'])
                ->middleware('abilities:caisse.session.view')
                ->name('caisse.sessions.summary');

            // Admin: force close
            Route::post('/sessions/{session}/force-close', [\App\Http\Controllers\Api\Caisse\CashSessionAdminController::class, 'forceClose'])
                ->middleware('abilities:caisse.session.manage')
                ->name('caisse.sessions.force_close');

            // Export CSV + ticket
            Route::get('/payments/export', [CashReportController::class, 'exportCsv'])
                ->middleware('abilities:caisse.report.view')
                ->name('caisse.payments.export');

            Route::get('/payments/{reglement}/ticket', [\App\Http\Controllers\Api\Caisse\PaymentTicketController::class, 'show'])
                ->middleware('abilities:caisse.report.view')
                ->name('caisse.payments.ticket');

            // Variante "register"
            Route::post('sessions/open/register',   [CashRegisterSessionController::class, 'open'])
                ->middleware('abilities:caisse.session.manage')
                ->name('caisse.register.open');

            Route::get('sessions/current/register', [CashRegisterSessionController::class, 'current'])
                ->middleware('abilities:caisse.session.view')
                ->name('caisse.register.current');

            Route::post('sessions/close/register',  [CashRegisterSessionController::class, 'close'])
                ->middleware('abilities:caisse.session.manage')
                ->name('caisse.register.close');
        });

    // ── FACTURES simples + lignes + règlements (avec contraintes caisse) ──
    Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {
        Route::apiResource('factures', FactureController::class)->only(['index','show','store']);

        Route::apiResource('factures.lignes', FactureLigneController::class)
            ->only(['store','update','destroy'])
            ->shallow();

        // Exiger session ouverte + portée service pour encaissement
        Route::apiResource('factures.reglements', ReglementController::class)
            ->only(['store'])
            ->middleware(['abilities:caisse.reglement.create', 'cashbox.open', 'cashbox.service'])
            ->shallow();

        Route::get('factures/{facture}/pdf', [FactureController::class, 'pdf'])
            ->name('factures.pdf');
    });

    // ── RDV & Planning Médecins ───────────────────────────────────────────────
   Route::middleware(['auth:sanctum','throttle:auth'])->group(function () {

    Route::get(   'medecins/{medecin}/planning',    [PlanningController::class,'index'])
        ->name('v1.medecins.planning.index');

    Route::post(  'medecins/{medecin}/planning',    [PlanningController::class,'store'])
        // ->middleware('ability:medecins.update')
        ->name('v1.medecins.planning.store');

    Route::post(  'medecins/{medecin}/exceptions',  [PlanningController::class,'addException'])
        // ->middleware('ability:medecins.update')
        ->name('v1.medecins.planning.exceptions.store');

    Route::get(   'medecins/{medecin}/disponibilites', [PlanningController::class,'disponibilites'])
        ->name('v1.medecins.disponibilites');

    Route::get(   'medecins/{medecin}/rendez-vous', [RendezVousController::class,'listByMedecin'])
        ->name('v1.medecins.rendez_vous.index');

    // 🔹 Nouveau : liste globale des rendez-vous (avec filtres éventuels dans index())
    Route::get(   'rendez-vous',               [RendezVousController::class,'index'])
        // ->middleware('ability:rendez_vous.view')
        ->name('v1.rendez_vous.index');

    Route::post(  'rendez-vous',               [RendezVousController::class,'store'])
        // ->middleware('ability:rendez_vous.create')
        ->name('v1.rendez_vous.store');

    Route::patch( 'rendez-vous/{rdv}',         [RendezVousController::class,'update'])
        // ->middleware('ability:rendez_vous.update')
        ->name('v1.rendez_vous.update');
});


});
