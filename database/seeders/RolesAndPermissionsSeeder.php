<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // (Re)set des caches Spatie au cas où
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // --- Permissions par domaine (exemples, adapte selon ton besoin)
        $perms = [
            // Accueil / Réception
            'patients.view', 'patients.create', 'patients.update', 'patients.orient',
            // Consultations
            'consultations.view', 'consultations.create', 'consultations.update',
            // Laboratoire
            'labo.request.create', 'labo.result.write', 'labo.view',
            // Pharmacie
            'pharma.stock.view', 'pharma.sale.create', 'pharma.ordonnance.validate',
            // Finance / Caisse
            'finance.invoice.view', 'finance.invoice.create', 'finance.payment.create',
            // Admin / Personnel
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.assign',
            // Statistiques
            'stats.view',
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // --- Rôles
        $roles = [
            'admin'          => $perms, // admin a tout
            'reception'      => ['patients.view','patients.create','patients.orient','stats.view'],
            'medecin'        => ['patients.view','consultations.view','consultations.create','consultations.update','stats.view'],
            'infirmier'      => ['patients.view','consultations.view','stats.view'],
            'laborantin'     => ['labo.view','labo.request.create','labo.result.write','stats.view'],
            'pharmacien'     => ['pharma.stock.view','pharma.sale.create','pharma.ordonnance.validate','stats.view'],
            'caissier'       => ['finance.invoice.view','finance.invoice.create','finance.payment.create','stats.view'],
            'gestionnaire'   => ['users.view','stats.view'],
        ];

        foreach ($roles as $roleName => $allowed) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($allowed);
        }

        // Re-cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
