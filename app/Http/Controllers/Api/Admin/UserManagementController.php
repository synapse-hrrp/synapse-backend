<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    /**
     * GET /api/v1/admin/users
     * Liste paginée, recherche, filtre par rôle, sortie avec roles[] + personnel(+service) + service_ids
     */
    public function index(Request $request)
    {
        $q = User::query()
            ->with([
                'roles:id,name',
                'personnel:id,user_id,first_name,last_name,service_id,matricule,job_title',
                'personnel.service:id,name',
                'services:id,name', // 👈 services via pivot user_service
            ])
            ->select('id','name','email','phone','is_active','created_at')
            ->when($request->search, fn($qq, $s) => $qq->search($s))
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

                // 👇 UTILISÉ PAR LE FRONT POUR LES CASES À COCHER
                'service_ids' => $u->services
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values(),

                // (optionnel) si tu veux aussi les noms côté front
                'services' => $u->services->map(function ($s) {
                    return [
                        'id'   => (int) $s->id,
                        'name' => $s->name,
                    ];
                })->values(),
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
            'password'              => ['required', Password::min(8) ],
            'password_confirmation' => ['required','same:password'],
            'phone'                 => ['nullable','string','max:30'],
            'roles'                 => ['nullable','array'],
            'roles.*'               => ['string','exists:roles,name'],

            // Préremplissage optionnel de la fiche personnel
            'first_name'            => ['sometimes','string','max:100'],
            'last_name'             => ['sometimes','string','max:100'],
            'service_id'            => ['nullable','exists:services,id'],
            'matricule'             => ['nullable','string','max:50','unique:personnels,matricule'],
        ]);

        // 1) Créer l'utilisateur
        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'phone'     => $data['phone'] ?? null,
            'is_active' => true,
        ]);

        // 2) Rôles
        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // 3) Déterminer first/last non nuls pour Personnel
        $first = isset($data['first_name']) ? trim((string)$data['first_name']) : '';
        $last  = isset($data['last_name'])  ? trim((string)$data['last_name'])  : '';

        if ($first === '' && $last === '') {
            $parts = array_values(array_filter(preg_split('/\s+/', trim($data['name']))));
            if (count($parts) >= 2) {
                $first = $parts[0];
                $last  = implode(' ', array_slice($parts, 1));
            }
        }

        if ($first === '') $first = 'Prénom';
        if ($last  === '') $last  = 'Nom';

        // 4) Auto-créer la fiche Personnel
        Personnel::firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $first,
                'last_name'  => $last,
                'service_id' => $request->integer('service_id') ?: null,
                'matricule'  => $data['matricule']
                    ?? ('EMP-'.now()->format('Y').'-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT)),
            ]
        );

        // 5) Charger relations pour la réponse
        $user->load([
            'roles:id,name',
            'personnel:id,user_id,first_name,last_name,service_id,matricule,job_title',
            'personnel.service:id,name',
            'services:id,name',
        ]);

        return response()->json([
            'message' => 'Utilisateur créé',
            'data'    => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'phone'       => $user->phone,
                'is_active'   => $user->is_active,
                'roles'       => $user->roles->pluck('name')->values(),
                'personnel'   => $user->personnel,
                'service_ids' => $user->services->pluck('id')->map(fn($id)=>(int)$id)->values(),
                'services'    => $user->services->map(fn($s)=>[
                    'id'   => (int)$s->id,
                    'name' => $s->name,
                ])->values(),
            ],
        ], 201);
    }

    /**
     * GET /api/v1/admin/users/{user}
     * Détail d’un user (avec rôles + personnel + services)
     */
    public function show(User $user)
    {
        $user->load([
            'roles:id,name',
            'personnel:id,user_id,first_name,last_name,service_id,matricule,job_title',
            'personnel.service:id,name',
            'services:id,name',
        ]);

        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'is_active'   => $user->is_active,
            'roles'       => $user->roles->pluck('name')->values(),
            'personnel'   => $user->personnel,
            'service_ids' => $user->services->pluck('id')->map(fn($id)=>(int)$id)->values(),
            'services'    => $user->services->map(fn($s)=>[
                'id'   => (int)$s->id,
                'name' => $s->name,
            ])->values(),
        ]);
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
            'services:id,name',
        ]);

        return response()->json([
            'message' => 'Utilisateur mis à jour',
            'data'    => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'phone'       => $user->phone,
                'is_active'   => $user->is_active,
                'roles'       => $user->roles->pluck('name')->values(),
                'personnel'   => $user->personnel,
                'service_ids' => $user->services->pluck('id')->map(fn($id)=>(int)$id)->values(),
                'services'    => $user->services->map(fn($s)=>[
                    'id'   => (int)$s->id,
                    'name' => $s->name,
                ])->values(),
            ],
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
