<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    /**
     * GET /api/v1/admin/personnels
     */
    public function index(Request $request)
    {
        $userId   = $request->query('user_id');
        $service  = $request->query('service_id');
        $q        = trim((string) $request->query('q', ''));
        $perPage  = (int) $request->query('per_page', 10);
        $perPage  = max(1, min($perPage, 100));

        $query = Personnel::query()->with(['user:id,name,email', 'service:id,name']);

        if (!empty($userId))   $query->where('user_id', $userId);
        if (!empty($service))  $query->where('service_id', $service);

        if ($q !== '') {
            $like = '%' . preg_replace('/\s+/', '%', $q) . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('matricule', 'like', $like)
                    ->orWhere('cin', 'like', $like)
                    ->orWhereHas('user', function ($u) use ($like) {
                        $u->where('name', 'like', $like)
                          ->orWhere('email', 'like', $like);
                    });
            });
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate($perPage)
        );
    }

    /**
     * POST /api/v1/admin/personnels
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'       => ['required','exists:users,id','unique:personnels,user_id'],
            'matricule'     => ['nullable','string','max:50','unique:personnels,matricule'],
            'first_name'    => ['required','string','max:100'],
            'last_name'     => ['required','string','max:100'],
            'sex'           => ['nullable','in:M,F'],
            'date_of_birth' => ['nullable','date'],
            'cin'           => ['nullable','string','max:100','unique:personnels,cin'],
            'phone_alt'     => ['nullable','string','max:30'],
            'address'       => ['nullable','string','max:255'],
            'city'          => ['nullable','string','max:100'],
            'country'       => ['nullable','string','max:100'],
            'job_title'     => ['nullable','string','max:150'],
            'hired_at'      => ['nullable','date'],
            'service_id'    => ['nullable','exists:services,id'],
            'roles'         => ['sometimes','array'],
            'roles.*'       => ['string','exists:roles,name'],
            'permissions'   => ['sometimes','array'],
            'permissions.*' => ['string','exists:permissions,name'],
        ]);

        // Séparer roles/permissions envoyés par le frontend
        $roles       = $validated['roles'] ?? [];
        $permissions = $validated['permissions'] ?? [];
        unset($validated['roles'], $validated['permissions']);

        $p = Personnel::create($validated);

        // Attribuer le rôle automatique selon le service si roles non fournis
        $serviceRoleMap = [
            'laboratoire' => 'laborantin',
            'pharmacie'   => 'pharmacien',
            'finance'     => 'caissier',
            'consultations' => 'medecin',
            'accueil'   => 'reception',
            'pansement'   => 'infirmier',
            'gestion-malade' => 'gestionnaire',
            // tu peux compléter selon les services et rôles existants
        ];

        $user = $p->user;
        if ($user) {
            if (!empty($roles)) {
                $user->syncRoles($roles);
            } elseif ($p->service && isset($serviceRoleMap[$p->service->slug])) {
                $user->syncRoles([$serviceRoleMap[$p->service->slug]]);
            }

            if (!empty($permissions)) {
                $user->syncPermissions($permissions);
            }
        }

        return response()->json([
            'message' => 'Personnel créé',
            'data'    => $p->load(['user:id,name,email','service:id,name']),
        ], 201);
    }

    /**
     * PATCH /api/v1/admin/personnels/{personnel}
     */
    public function update(Request $request, Personnel $personnel)
    {
        $payload = $request->all();
        if (array_key_exists('phone', $payload)) {
            $payload['phone_alt'] = $payload['phone'];
            unset($payload['phone']);
        }
        if (array_key_exists('hire_date', $payload)) {
            $payload['hired_at'] = $payload['hire_date'];
            unset($payload['hire_date']);
        }
        unset($payload['user_id']);

        $validated = validator($payload, [
            'matricule'     => ['nullable','string','max:50','unique:personnels,matricule,'.$personnel->id],
            'first_name'    => ['sometimes','string','max:100'],
            'last_name'     => ['sometimes','string','max:100'],
            'sex'           => ['nullable','in:M,F'],
            'date_of_birth' => ['nullable','date'],
            'cin'           => ['nullable','string','max:100','unique:personnels,cin,'.$personnel->id],
            'phone_alt'     => ['nullable','string','max:30'],
            'address'       => ['nullable','string','max:255'],
            'city'          => ['nullable','string','max:100'],
            'country'       => ['nullable','string','max:100'],
            'job_title'     => ['nullable','string','max:150'],
            'hired_at'      => ['nullable','date'],
            'service_id'    => ['nullable','exists:services,id'],
            'roles'         => ['sometimes','array'],
            'roles.*'       => ['string','exists:roles,name'],
            'permissions'   => ['sometimes','array'],
            'permissions.*' => ['string','exists:permissions,name'],
        ])->validate();

        $roles       = $validated['roles'] ?? null;
        $permissions = $validated['permissions'] ?? null;
        unset($validated['roles'], $validated['permissions']);

        $personnel->update($validated);

        $serviceRoleMap = [
            'laboratoire' => 'laborantin',
            'pharmacie'   => 'pharmacien',
            'finance'     => 'caissier',
            'consultations' => 'medecin',
            'reception'   => 'reception',
            'pansement'   => 'infirmier',
            'gestion-malade' => 'gestionnaire',
        ];

        $user = $personnel->user;
        if ($user) {
            if ($roles !== null) {
                $user->syncRoles($roles);
            } elseif ($personnel->service && isset($serviceRoleMap[$personnel->service->slug])) {
                $user->syncRoles([$serviceRoleMap[$personnel->service->slug]]);
            }

            if ($permissions !== null) {
                $user->syncPermissions($permissions);
            }
        }

        return response()->json([
            'message' => 'Personnel modifié',
            'data'    => $personnel->load(['user:id,name,email','service:id,name']),
        ]);
    }

    // Les autres méthodes show() et destroy() restent inchangées
        /**
     * GET /api/v1/admin/personnels/{personnel}
     */
    public function show(Personnel $personnel)
    {
        return response()->json(
            $personnel->load(['user:id,name,email', 'service:id,name'])
        );
    }

    /**
     * DELETE /api/v1/admin/personnels/{personnel}
     */
    public function destroy(Personnel $personnel)
    {
        $personnel->delete();

        return response()->json([
            'message' => 'Personnel supprimé avec succès'
        ]);
    }

}