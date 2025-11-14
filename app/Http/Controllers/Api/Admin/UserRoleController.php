<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\User;

class UserRoleController extends Controller
{
    /**
     * POST /api/v1/admin/users/{user}/roles
     * Body JSON: { "roles": ["caissier_service","caissier_general", ...] }
     */
    public function syncRoles(Request $request, User $user)
    {
        $this->authorizeAction($request);

        $data = $request->validate([
            'roles'   => ['array'],
            'roles.*' => ['string'],
        ]);

        // Normalise et filtre sur les rôles existants (peu importe le guard)
        $roles = array_values(array_unique(array_map('trim', $data['roles'] ?? [])));
        $existing = !empty($roles)
            ? Role::whereIn('name', $roles)->pluck('name')->all()
            : [];

        // Sync des rôles Spatie
        $user->syncRoles($existing);

        return response()->json([
            'message' => 'Roles synced.',
            'data' => [
                'id'          => $user->id,
                'roles'       => $user->roles()->pluck('name')->values(),
                // renvoie aussi les services pour garder les coches côté front
                'service_ids' => DB::table('user_service')->where('user_id', $user->id)->pluck('service_id')->values(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/users/{user}/services
     * Body JSON: { "service_ids": [1,2,3] }
     */
    public function syncServices(Request $request, User $user)
    {
        $this->authorizeAction($request);

        $data = $request->validate([
            'service_ids'   => ['array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        $ids = array_values(array_unique($data['service_ids'] ?? []));

        // Table pivot "user_service" (user_id, service_id)
        DB::table('user_service')->where('user_id', $user->id)->delete();

        if (!empty($ids)) {
            $rows = array_map(
                fn ($sid) => ['user_id' => $user->id, 'service_id' => $sid],
                $ids
            );
            DB::table('user_service')->insert($rows);
        }

        return response()->json([
            'message' => 'Services synced.',
            'data' => [
                'id'          => $user->id,
                'roles'       => $user->roles()->pluck('name')->values(),
                'service_ids' => DB::table('user_service')->where('user_id', $user->id)->pluck('service_id')->values(),
            ],
        ]);
    }

    /**
     * Autorisation robuste SANS mismatch de guard:
     *  - rôle: admin OU admin_caisse
     *  - OU permission 'roles.assign' (directe ou via rôle)
     */
    private function authorizeAction(Request $request): void
    {
        $actor = $request->user();
        if (!$actor) abort(401, 'Unauthenticated');

        // Rôles (insensibles au guard via jointure DB)
        $roleNames = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', get_class($actor))
            ->where('model_has_roles.model_id', $actor->getKey())
            ->pluck('roles.name')
            ->map(fn($n) => strtolower($n))
            ->all();

        if (in_array('admin', $roleNames, true) || in_array('admin_caisse', $roleNames, true)) {
            return;
        }

        // Permission 'roles.assign' directe
        $hasDirectPerm = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', get_class($actor))
            ->where('model_has_permissions.model_id', $actor->getKey())
            ->whereRaw('LOWER(permissions.name) = ?', ['roles.assign'])
            ->exists();

        // Permission via rôle
        $actorRoleIds = DB::table('model_has_roles')
            ->where('model_type', get_class($actor))
            ->where('model_id', $actor->getKey())
            ->pluck('role_id')
            ->all();

        $hasPermViaRole = false;
        if (!empty($actorRoleIds)) {
            $hasPermViaRole = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->whereIn('role_has_permissions.role_id', $actorRoleIds)
                ->whereRaw('LOWER(permissions.name) = ?', ['roles.assign'])
                ->exists();
        }

        if ($hasDirectPerm || $hasPermViaRole) {
            return;
        }

        abort(403, 'This action is unauthorized.');
    }
}
