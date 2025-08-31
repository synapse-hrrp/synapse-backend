<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    /**
     * GET /api/v1/admin/users
     * Liste paginée, recherche, filtre par rôle, sortie avec roles[] et is_admin
     */
    public function index(Request $request)
    {
        $q = User::query()
            ->with('roles:id,name') // charger les rôles
            ->select('id','name','email','phone','is_active','created_at')
            ->when($request->search, fn($qq,$s)=>$qq->search($s))
            // Filtre optionnel par rôle: /api/v1/admin/users?role=admin
            ->when($request->filled('role'), function ($qq) use ($request) {
                $qq->whereHas('roles', fn($r)=>$r->where('name', $request->string('role')));
            })
            ->latest('id');

        $page = $q->paginate($request->integer('per_page',10));

        // Transformer chaque item pour ajouter roles + is_admin
        $page->through(function (User $u) {
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone,
                'is_active'  => $u->is_active,
                //'service_id' => $u->service_id,
                'created_at' => $u->created_at,
                'roles'      => $u->roles->pluck('name')->values(),
                'is_admin'   => $u->hasAnyRole(['admin','dg']),
            ];
        });

        return response()->json($page);
    }

    /**
     * POST /api/v1/admin/users
     * Création d’un user + assignation des rôles
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
        ]);

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'phone'      => $data['phone'] ?? null,
            'is_active'  => true,
        ]);

        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return response()->json([
            'message' => 'Utilisateur créé',
            'data'    => $user->load('roles'),
        ], 201);
    }

    /**
     * GET /api/v1/admin/users/{user}
     * Détail d’un user (avec rôles)
     */
    public function show(User $user)
    {
        return response()->json(
            $user->load(['roles','service'])
        );
    }

    /**
     * PUT/PATCH /api/v1/admin/users/{user}
     * Mise à jour infos + rôles (password optionnel)
     */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'       => ['sometimes','string','max:255'],
            'email'      => ['sometimes','email','max:255','unique:users,email,'.$user->id],
            'phone'      => ['nullable','string','max:30'],
            'is_active'  => ['sometimes','boolean'],
            'password'   => ['nullable', Password::min(8)],
            'roles'      => ['nullable','array'],
            'roles.*'    => ['string','exists:roles,name'],
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

        return response()->json([
            'message' => 'Utilisateur mis à jour',
            'data'    => $user->load('roles'),
        ]);
    }

    /**
     * DELETE /api/v1/admin/users/{user}
     * Suppression (soft delete si activé, sinon hard delete)
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé']);
    }
}
