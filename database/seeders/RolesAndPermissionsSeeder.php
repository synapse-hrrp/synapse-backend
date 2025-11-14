<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset du cache Spatie (avant et après)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        // Abilities Caisse indispensables (middlewares & ServiceAccess::isGlobal)
        $caisseAbilities = [
            'caisse.access',
            'caisse.session.manage',
            'caisse.session.view',
            'caisse.report.view',
            'caisse.reglement.create',
            'caisse.audit.view',      // ➕ pour Admin Caisse
            'caisse.report.global',   // ➕ donne la portée transversale (tous services)
        ];

        // Permissions “app” existantes (on reprend les tiennes)
        $perms = [
            // 🌟 Alias simples (lecture/écriture)
            'patients.read', 'patients.write',
            'visites.read',  'visites.write',

            // ➕ lookups front
            'medecins.read', 'personnels.read', 'services.read', 'tarifs.read',

            // Patients
            'patients.view', 'patients.create', 'patients.update', 'patients.delete', 'patients.orient',

            // Consultations
            'consultations.view', 'consultations.create', 'consultations.update', 'consultations.delete',

            // Examens
            'examen.view', 'examen.request.create', 'examen.result.write', 'examen.create',

            // Examens par service
            'medecine.examen.create','aru.examen.create','gynecologie.examen.create','maternite.examen.create',
            'pediatrie.examen.create','sanitaire.examen.create','consultations.examen.create','smi.examen.create',

            // Tarifs
            'tarif.view', 'tarif.create', 'tarif.update', 'tarif.delete',
            'tarifs.view', 'tarifs.create', 'tarifs.update', 'tarifs.delete',

            // Pharmacie
            'pharma.stock.view', 'pharma.sale.create', 'pharma.ordonnance.validate',

            // Finance / Caisse (existants)
            'caisse.facture.view', 'caisse.facture.create',
            'caisse.reglement.view', 'caisse.reglement.create', 'caisse.reglement.validate',

            // Pansements
            'pansement.view', 'pansement.create', 'pansement.update', 'pansement.delete',

            // Médecins / Personnels / Services (CRUDs)
            'medecins.view', 'medecins.create', 'medecins.update', 'medecins.delete',
            'personnels.view', 'personnels.create', 'personnels.update', 'personnels.delete',
            'services.view', 'services.create', 'services.update', 'services.delete',

            // Autres modules de soins
            'aru.view', 'aru.create', 'aru.update', 'aru.delete',
            'medecine.view', 'medecine.create', 'medecine.update', 'medecine.delete',
            'kinesitherapie.view', 'kinesitherapie.create', 'kinesitherapie.update', 'kinesitherapie.delete',
            'gestion-malade.view', 'gestion-malade.create', 'gestion-malade.update', 'gestion-malade.delete',
            'sanitaire.view', 'sanitaire.create', 'sanitaire.update', 'sanitaire.delete',
            'gynecologie.view', 'gynecologie.create', 'gynecologie.update', 'gynecologie.delete',
            'maternite.view', 'maternite.create', 'maternite.update', 'maternite.delete',
            'pediatrie.view', 'pediatrie.create', 'pediatrie.update', 'pediatrie.delete',
            'smi.view', 'smi.create', 'smi.update', 'smi.delete',
            'bloc-operatoire.view', 'bloc-operatoire.create', 'bloc-operatoire.update', 'bloc-operatoire.delete',
            'logistique.view', 'logistique.create', 'logistique.update', 'logistique.delete',

            // Admin / Personnel
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.assign',

            // Statistiques
            'stats.view',

            // Pourcentage
            'pourcentage.view', 'pourcentage.update',
        ];

        // Ajout des abilities Caisse
        $perms = array_values(array_unique(array_merge($perms, $caisseAbilities)));

        // Création/synchro des permissions
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        // -----------------------------
        //  RÔLES  (inclut les 3 caisses)
        // -----------------------------
        $roles = [
            // Admin : toutes les permissions
            'admin' => $perms,

            // Réception
            'reception' => [
                'patients.read','patients.write',
                'patients.view','patients.create','patients.orient',
                'visites.read','visites.write',
                'medecins.read','personnels.read','services.read','tarifs.read',
                'stats.view',
            ],

            // Médecin
            'medecin' => [
                'patients.read',
                'consultations.view','consultations.create','consultations.update',
                'examen.create',
                'medecine.examen.create','aru.examen.create','gynecologie.examen.create',
                'maternite.examen.create','pediatrie.examen.create','sanitaire.examen.create',
                'consultations.examen.create','smi.examen.create',
                'stats.view',
            ],

            // Infirmier
            'infirmier' => [
                'patients.read',
                'pansement.view','pansement.create','pansement.update',
                'stats.view',
            ],

            // Laborantin
            'laborantin' => [
                'examen.view','examen.create','examen.request.create','examen.result.write',
                'stats.view','patients.read',
                'services.read','tarifs.read','medecins.read',
            ],

            // Pharmacien
            'pharmacien' => [
                'pharma.stock.view','pharma.sale.create','pharma.ordonnance.validate',
                'stats.view',
            ],

            // ✅ Caissier (rôle existant – on le garde)
            'caissier' => [
                'caisse.access',
                'caisse.session.view',
                'caisse.session.manage',
                'caisse.report.view',
                'caisse.reglement.view',
                'caisse.reglement.create',
                'caisse.reglement.validate',
                'visites.read','stats.view',
                'services.read','tarifs.read','medecins.read','personnels.read',
            ],

            // ✅ 1) CAISSE DE SERVICE (limité aux services liés à l'utilisateur)
            'caissier_service' => [
                'caisse.access',
                'caisse.session.view',
                'caisse.session.manage',
                'caisse.reglement.view',
                'caisse.reglement.create',
                // pas de 'caisse.report.global' ⇒ filtrage par ServiceAccess
                'caisse.report.view',
            ],

            // ✅ 2) CAISSE GÉNÉRALE (lecture/encaissement multi-service)
            'caissier_general' => [
                'caisse.access',
                'caisse.session.view',
                'caisse.session.manage',
                'caisse.reglement.view',
                'caisse.reglement.create',
                'caisse.report.view',
                'caisse.report.global',  // ⇒ portée globale dans ServiceAccess
            ],

            // ✅ 3) ADMIN CAISSE (audit & global)
            'admin_caisse' => [
                'caisse.access',
                'caisse.session.view',
                'caisse.report.view',
                'caisse.report.global',  // ⇒ portée globale
                'caisse.audit.view',     // journal d'audit
                // (facultatif) pas forcément encaissement
            ],

            // Gestionnaire
            'gestionnaire' => [
                'users.view',
                'stats.view',
            ],
        ];

        // Création/synchro des rôles
        foreach ($roles as $roleName => $allowed) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => $guard],
                ['name' => $roleName, 'guard_name' => $guard]
            );

            $allowedWithGuard = Permission::whereIn('name', $allowed)
                ->where('guard_name', $guard)
                ->get();

            $role->syncPermissions($allowedWithGuard);
        }

        // Re-cache final
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
