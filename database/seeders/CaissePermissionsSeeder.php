<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Role, Permission};

class CaissePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // — Permissions "caisse"
        $perms = [
            'caisse.access',          // pouvoir appeler les routes caisse
            'caisse.session.manage',  // ouvrir/fermer sa session
            'caisse.session.view',    // voir sa session
            'caisse.report.view',     // voir les rapports
            'caisse.audit.view',      // voir le journal d'audit
        ];

        foreach ($perms as $p) {
            Permission::findOrCreate($p);
        }

        // — Rôles
        // 1) Caissier de service (encaisse uniquement son service)
        $roleService = Role::findOrCreate('caissier_service');
        $roleService->syncPermissions([
            'caisse.access',
            'caisse.session.manage',
            'caisse.session.view',
        ]);

        // 2) Caissier général (lecture transversale + encaissement multi-service si on l’autorise)
        $roleGeneral = Role::findOrCreate('caissier_general');
        $roleGeneral->syncPermissions([
            'caisse.access',
            'caisse.session.manage',
            'caisse.session.view',
            'caisse.report.view',
        ]);

        // 3) Admin caisse (audit/rapports)
        $roleAdminCaisse = Role::findOrCreate('admin_caisse');
        $roleAdminCaisse->syncPermissions([
            'caisse.access',
            'caisse.session.view',
            'caisse.report.view',
            'caisse.audit.view',
        ]);

        // 4) Option: rattacher à ton rôle "admin" général existant
        $admin = Role::where('name','admin')->first();
        if ($admin) {
            $admin->givePermissionTo($perms);
        }
    }
}
