<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()
            ->select('id','name','email','phone','is_active','service_id','created_at')
            ->when($request->search, fn($qq,$s)=>$qq->search($s))
            ->latest('id');

        return response()->json($q->paginate($request->integer('per_page',10)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                  => ['required','string','max:255'],
            'email'                 => ['required','email','max:255','unique:users,email'],
            'password'              => ['required', Password::min(8)],
            'password_confirmation' => ['required','same:password'],
            'phone'                 => ['nullable','string','max:30'],
            'service_id'            => ['nullable','integer','exists:services,id'],
            'roles'                 => ['nullable','array'],
            'roles.*'               => ['string','exists:roles,name'],
        ]);

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'phone'      => $data['phone'] ?? null,
            'service_id' => $data['service_id'] ?? null,
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
}
