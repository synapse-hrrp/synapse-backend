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

        // Guard utilisé pour les rôles/permissions
        $guard = 'web';

        // --- Permissions par domaine
        $perms = [
            // 🌟 Alias simples (lecture/écriture)
            'patients.read', 'patients.write',
            'visites.read',  'visites.write',

            // Patients (granulaire – on les garde)
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

        // --- Rôles (répartition cohérente)
        $roles = [
            // Admin : tout
            'admin' => $perms,

            // Réception : crée/voit patients & visites
            'reception' => [
                'patients.read','patients.write',
                'patients.view','patients.create','patients.orient',
                'visites.read','visites.write',
                'stats.view',
            ],

            // Médecin : lecture/écriture patients & visites, consultations
            'medecin' => [
                'patients.read',
                'visites.read','visites.write',
                'consultations.view','consultations.create','consultations.update',
                'stats.view',
            ],

            // Infirmier : lecture patients, écritures pansements & visites
            'infirmier' => [
                'patients.read',
                'visites.read','visites.write',
                'pansement.view','pansement.create','pansement.update',
                'stats.view',
            ],

            // Laborantin : labo + lecture visites
            'laborantin' => [
                'labo.view','labo.request.create','labo.result.write',
                'visites.read',
                'stats.view',
            ],

            // Pharmacien : pharmacie + lecture visites
            'pharmacien' => [
                'pharma.stock.view','pharma.sale.create','pharma.ordonnance.validate',
                'visites.read',
                'stats.view',
            ],

            // Caissier : finance + lecture visites
            'caissier' => [
                'finance.invoice.view','finance.invoice.create','finance.payment.create',
                'visites.read',
                'stats.view',
            ],

            // Gestionnaire : gestion des users + stats + lecture visites
            'gestionnaire' => [
                'users.view',
                'visites.read',
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