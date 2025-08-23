<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Throwable;

class UserManagementController extends Controller
{
    public function index(StoreUserRequest $request): JsonResponse
    {
        $q = User::query()
            ->select('id','name','email','phone','is_active','service_id','created_at')
            ->when($request->input('search'), fn($qq,$s) => $qq->search($s))
            ->latest('id');

        return response()->json($q->paginate($request->integer('per_page', 10)));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $user = User::create([
                'name'       => $data['name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'phone'      => $data['phone'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'is_active'  => true,
            ]);

            // Rôles (optionnels)
            if (!empty($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            // Permissions (optionnelles)
            if (!empty($data['permissions'])) {
                $user->givePermissionTo($data['permissions']); // ajoute à l’utilisateur
            }

            return response()->json([
                'message' => 'Utilisateur créé',
                'data'    => [
                    'user'  => $user->only(['id','name','email','phone','service_id','is_active']),
                    'roles' => $user->getRoleNames(),
                    'perms' => $user->getPermissionNames(),
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de la création',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
