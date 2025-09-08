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

        // Choisis explicitement le guard pour tes rôles/permissions.
        // Par défaut, on reste sur 'web'. Si tu veux un guard API, change 'web' par 'api'.
        $guard = 'web';

        // --- Permissions par domaine
        $perms = [
            // Patients
            'patients.view', 'patients.create', 'patients.update', 'patients.delete', 'patients.orient',

            // Consultations
            'consultations.view', 'consultations.create', 'consultations.update', 'consultations.delete',

            // Laboratoire
            'labo.request.create', 'labo.result.write', 'labo.view',

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

        // Créer / maj les permissions avec guard_name
        foreach ($perms as $p) {
            Permission::firstOrCreate(
                ['name' => $p, 'guard_name' => $guard],
                ['name' => $p, 'guard_name' => $guard]
            );
        }

        // --- Rôles
        $roles = [
            'admin'        => $perms, // admin a tout
            'reception'    => ['patients.view','patients.create','patients.orient','stats.view'],
            'medecin'      => ['patients.view','consultations.view','consultations.create','consultations.update','stats.view'],
            'infirmier'    => ['patients.view','consultations.view','pansement.view','pansement.create','pansement.update','stats.view'],
            'laborantin'   => ['labo.view','labo.request.create','labo.result.write','stats.view'],
            'pharmacien'   => ['pharma.stock.view','pharma.sale.create','pharma.ordonnance.validate','stats.view'],
            'caissier'     => ['finance.invoice.view','finance.invoice.create','finance.payment.create','stats.view'],
            'gestionnaire' => ['users.view','stats.view'],
        ];

        foreach ($roles as $roleName => $allowed) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => $guard],
                ['name' => $roleName, 'guard_name' => $guard]
            );
            // On s’assure que les permissions référencées existent bien pour ce guard
            $allowedWithGuard = Permission::whereIn('name', $allowed)
                ->where('guard_name', $guard)
                ->get();

            $role->syncPermissions($allowedWithGuard);
        }

        // Re-cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
