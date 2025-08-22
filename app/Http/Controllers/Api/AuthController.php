<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            // optionnel: 'device_name' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Compte inactif.'], 403);
        }

        // Crée un token Sanctum (Personal Access Token)
        $abilities = ['*']; // tu peux mettre des abilities spécifiques si tu veux
        $device = $request->input('device_name', 'postman');

        $token = $user->createToken($device, $abilities);

        // Bonus: enregistrer l’IP/heure de dernière connexion
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'message'     => 'Connexion réussie.',
            'token'       => $token->plainTextToken,
            'token_type'  => 'Bearer',
            'user'        => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->getRoleNames(),           // Spatie
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    public function me(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'id'          => $u->id,
            'name'        => $u->name,
            'email'       => $u->email,
            'roles'       => $u->getRoleNames(),
            'permissions' => $u->getAllPermissions()->pluck('name'),
        ]);
    }

    public function logout(Request $request)
    {
        // Supprime uniquement le token courant
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }
}
