<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Personnel; // ✅ auto-création de la fiche RH
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    /**
     * GET /api/v1/admin/users
     * Liste paginée, recherche, filtre par rôle, sortie avec roles[] + personnel(+service)
     */
    public function index(Request $request)
    {
        $q = User::query()
            ->with([
                'roles:id,name',
                'personnel:id,user_id,first_name,last_name,service_id,matricule,job_title',
                'personnel.service:id,name',
            ])
            ->select('id','name','email','phone','is_active','created_at')
            ->when($request->search, fn($qq,$s) => $qq->search($s))
            ->when($request->filled('role'), function ($qq) use ($request) {
                $qq->whereHas('roles', fn($r) => $r->where('name', $request->string('role')));
            })
            ->latest('id');

        $page = $q->paginate($request->integer('per_page', 10));

        $page->through(function (User $u) {
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone,
                'is_active'  => $u->is_active,
                'created_at' => $u->created_at,
                'roles'      => $u->roles->pluck('name')->values(),
                'is_admin'   => $u->hasAnyRole(['admin','dg']),
                'personnel'  => $u->personnel ? [
                    'first_name' => $u->personnel->first_name,
                    'last_name'  => $u->personnel->last_name,
                    'matricule'  => $u->personnel->matricule,
                    'job_title'  => $u->personnel->job_title,
                    'service'    => $u->personnel->service ? [
                        'id'   => $u->personnel->service->id,
                        'name' => $u->personnel->service->name,
                    ] : null,
                ] : null,
            ];
        });

        return response()->json($page);
    }

    /**
     * POST /api/v1/admin/users
     * Création d’un user + assignation des rôles + auto-création Personnel
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                  => ['required','string','max:255'],
            'email'                 => ['required','email','max:255','unique:users,email'],
            'password'              => ['required', Password::min(8)],
            'password_confirmation' => ['required','same:password'],
            'phone'                 => ['nullable','string','max:30'],
            'roles'                 => ['nullable','array'],
            'roles.*'               => ['string','exists:roles,name'],

            // champs optionnels pour préremplir la fiche personnel
            'service_id'            => ['nullable','exists:services,id'],
            'matricule'             => ['nullable','string','max:50','unique:personnels,matricule'],
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'phone'     => $data['phone'] ?? null,
            'is_active' => true,
        ]);

        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // ---------- auto-création Personnel ----------
        [$first, $last] = (function (string $full) {
            $parts = array_values(array_filter(preg_split('/\s+/', trim($full))));
            $first = $parts[0] ?? 'Prénom';
            $last  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Nom';
            return [$first, $last];
        })($data['name']);

        Personnel::firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $first,
                'last_name'  => $last,
                'service_id' => $request->integer('service_id') ?: null,
                'matricule'  => $data['matricule'] ?? ('EMP-'.now()->format('Y').'-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT)),
            ]
        );
        // ---------------------------------------------

        $user->load([
            'roles:id,name',
            'personnel:id,user_id,first_name,last_name,service_id,matricule,job_title',
            'personnel.service:id,name',
        ]);

        return response()->json([
            'message' => 'Utilisateur créé',
            'data'    => $user,
        ], 201);
    }

    /**
     * GET /api/v1/admin/users/{user}
     * Détail d’un user (avec rôles + personnel + service)
     */
    public function show(User $user)
    {
        $user->load([
            'roles:id,name',
            'personnel:id,user_id,first_name,last_name,service_id,matricule,job_title',
            'personnel.service:id,name',
        ]);

        return response()->json($user);
    }

    /**
     * PUT/PATCH /api/v1/admin/users/{user}
     * Mise à jour infos + rôles (password optionnel)
     */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => ['sometimes','string','max:255'],
            'email'     => ['sometimes','email','max:255','unique:users,email,'.$user->id],
            'phone'     => ['nullable','string','max:30'],
            'is_active' => ['sometimes','boolean'],
            'password'  => ['nullable', Password::min(8)],
            'roles'     => ['nullable','array'],
            'roles.*'   => ['string','exists:roles,name'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
        }

        $user->load([
            'roles:id,name',
            'personnel:id,user_id,first_name,last_name,service_id,matricule,job_title',
            'personnel.service:id,name',
        ]);

        return response()->json([
            'message' => 'Utilisateur mis à jour',
            'data'    => $user,
        ]);
    }

    /**
     * DELETE /api/v1/admin/users/{user}
     */
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé']);
    }
}
