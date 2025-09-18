<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    /**
     * GET /api/v1/admin/personnels
     * Liste paginée + filtres (user_id, service_id, recherche texte).
     */
    public function index(Request $request)
    {
        $userId   = $request->query('user_id');            // ?user_id=3
        $service  = $request->query('service_id');         // ?service_id=2
        $q        = trim((string) $request->query('q', ''));// ?q=alice
        $perPage  = (int) $request->query('per_page', 10);
        $perPage  = max(1, min($perPage, 100));

        $query = Personnel::query()->with(['user:id,name,email', 'service:id,name']);

        // Filtres exacts
        if (!empty($userId))   $query->where('user_id', $userId);
        if (!empty($service))  $query->where('service_id', $service);

        // Recherche texte (nom, prénom, matricule, cin, email user)
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

        $items = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($items);
    }

    /**
     * GET /api/v1/admin/personnels/by-user/{user_id}
     * Retourne la fiche personnel liée à un user (relation 1–1).
     */
    public function byUser(int $user_id)
    {
        $personnel = Personnel::with(['user:id,name,email', 'service:id,name'])
            ->where('user_id', $user_id)
            ->firstOrFail();

        return response()->json($personnel);
    }

    /**
     * POST /api/v1/admin/personnels
     */
    public function store(Request $request)
    {
        $data = $request->validate([
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

            // rôles & permissions à appliquer au user lié
            'roles'         => ['sometimes','array'],
            'roles.*'       => ['string','exists:roles,name'],
            'permissions'   => ['sometimes','array'],
            'permissions.*' => ['string','exists:permissions,name'],
        ]);

        $p = Personnel::create($data);

        // rôles/permissions → user lié
        if (!empty($data['roles']) || !empty($data['permissions'])) {
            $user = $p->user()->first();
            if ($user) {
                if (!empty($data['roles']))       $user->syncRoles($data['roles']);
                if (!empty($data['permissions'])) $user->syncPermissions($data['permissions']);
            }
        }

        return response()->json([
            'message' => 'Personnel créé',
            'data'    => $p->load(['user:id,name,email','service:id,name']),
        ], 201);
    }

    /**
     * GET /api/v1/admin/personnels/{personnel}
     */
    public function show(Personnel $personnel)
    {
        return response()->json(
            $personnel->load(['user:id,name,email','service:id,name'])
        );
    }

    /**
     * PATCH /api/v1/admin/personnels/{personnel}
     */
    public function update(Request $request, Personnel $personnel)
    {
        $payload = $request->all();

        // Aliases acceptés en entrée
        if (array_key_exists('phone', $payload)) {
            $payload['phone_alt'] = $payload['phone'];
            unset($payload['phone']);
        }
        if (array_key_exists('hire_date', $payload)) {
            $payload['hired_at'] = $payload['hire_date'];
            unset($payload['hire_date']);
        }
        unset($payload['user_id']); // interdit de changer l'user lié

        $data = validator($payload, [
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

            // optionnel : synchro rôles/permissions du user lié
            'roles'         => ['sometimes','array'],
            'roles.*'       => ['string','exists:roles,name'],
            'permissions'   => ['sometimes','array'],
            'permissions.*' => ['string','exists:permissions,name'],
        ])->validate();

        $personnel->update($data);

        if (array_key_exists('roles', $data) || array_key_exists('permissions', $data)) {
            $user = $personnel->user()->first();
            if ($user) {
                if (array_key_exists('roles', $data))       $user->syncRoles($data['roles'] ?? []);
                if (array_key_exists('permissions', $data)) $user->syncPermissions($data['permissions'] ?? []);
            }
        }

        return response()->json([
            'message' => 'Personnel modifié',
            'data'    => $personnel->load(['user:id,name,email','service:id,name']),
        ]);
    }

    /**
     * DELETE /api/v1/admin/personnels/{personnel}
     */
    public function destroy(Personnel $personnel)
    {
        $personnel->delete();
        return response()->json(['message' => 'Personnel supprimé']);
    }
}
