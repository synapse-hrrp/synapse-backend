<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Vider le cache Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        // --- Permissions par domaine
        $perms = [
            // 🌟 Alias simples (lecture/écriture)
            'patients.read', 'patients.write',
            'visites.read',  'visites.write',

            // Patients
            'patients.view', 'patients.create', 'patients.update', 'patients.delete', 'patients.orient',

            // Consultations
            'consultations.view', 'consultations.create', 'consultations.update', 'consultations.delete',

            // Examens
            'examen.view', 'examen.request.create', 'examen.result.write', 'examen.create',

            // Examens par service
            'medecine.examen.create',
            'aru.examen.create',
            'gynecologie.examen.create',
            'maternite.examen.create',
            'pediatrie.examen.create',
            'sanitaire.examen.create',
            'consultations.examen.create',
            'smi.examen.create',

            // Tarifs (gestion des prix par l’admin)
            'tarif.view', 'tarif.create', 'tarif.update', 'tarif.delete',

            // Pharmacie
            'pharma.stock.view', 'pharma.sale.create', 'pharma.ordonnance.validate',

            // Finance / Caisse
            'finance.invoice.view', 'finance.invoice.create', 'finance.payment.create',

            // Pansements
            'pansement.view', 'pansement.create', 'pansement.update', 'pansement.delete',

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

        // Création / mise à jour des permissions
        foreach ($perms as $p) {
            Permission::firstOrCreate(
                ['name' => $p, 'guard_name' => $guard],
                ['name' => $p, 'guard_name' => $guard]
            );
        }

        // --- Rôles
        $roles = [
            // Admin : tout
            'admin' => $perms,

            // Réception
            'reception' => [
                'patients.read','patients.write',
                'patients.view','patients.create','patients.orient',
                'visites.read','visites.write',
                'stats.view',
            ],

            // Médecin
            'medecin' => [
                'patients.read',
                'consultations.view','consultations.create','consultations.update',
                'examen.create',
                'medecine.examen.create','aru.examen.create','gynecologie.examen.create',
                'maternite.examen.create','pediatrie.examen.create','sanitaire.examen.create','consultations.examen.create','smi.examen.create',
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
                'stats.view',
            ],

            // Pharmacien
            'pharmacien' => [
                'pharma.stock.view','pharma.sale.create','pharma.ordonnance.validate',
                'stats.view',
            ],

            // Caissier
            'caissier' => [
                'finance.invoice.view','finance.invoice.create','finance.payment.create',
                'visites.read',
                'stats.view',
            ],

            // Gestionnaire
            'gestionnaire' => [
                'users.view',
                'stats.view',
            ],
        ];

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

        // Re-cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
