<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'       => ['required','email'],
            'password'    => ['required','string'],
            'device_name' => ['nullable','string','max:50'],
        ]);

        $email = strtolower(trim($data['email']));
        $user  = User::where('email', $email)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            Log::channel('security')->warning('login.failed', [
                'email'      => $email,
                'ip'         => $request->ip(),
                'user_agent' => substr((string)$request->userAgent(), 0, 255),
            ]);
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        if (!$user->is_active) {
            Log::channel('security')->warning('login.blocked', [
                'user_id'    => $user->id,
                'email'      => $user->email,
                'ip'         => $request->ip(),
                'user_agent' => substr((string)$request->userAgent(), 0, 255),
            ]);
            return response()->json(['message' => 'Compte inactif.'], 403);
        }

        // Abilities : si Spatie est présent on utilise ses permissions, sinon wildcard
        $abilities = method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions()->pluck('name')->all()
            : ['*'];

        $device    = $data['device_name'] ?? 'api';
        $expiresAt = now()->addHours(2);

        // Sanctum: createToken(name, abilities = ['*'], expiresAt = null)
        $newToken  = $user->createToken($device, $abilities, $expiresAt);

        // Optionnel recommandé : stocker IP / User-Agent du token si colonnes présentes
        try {
            $accessTokenModel = $newToken->accessToken; // \Laravel\Sanctum\PersonalAccessToken
            if ($accessTokenModel) {
                if (Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                    $accessTokenModel->ip_address = $request->ip();
                }
                if (Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                    $accessTokenModel->user_agent = substr((string)$request->userAgent(), 0, 255);
                }
                $accessTokenModel->save();
            }
        } catch (\Throwable $e) {
            // non bloquant
        }

        // Bonus : dernière connexion
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Log::channel('security')->info('login.ok', [
            'user_id'    => $user->id,
            'email'      => $user->email,
            'ip'         => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'message'     => 'Connexion réussie.',
            'token'       => $newToken->plainTextToken,
            'token_type'  => 'Bearer',
            'expires_at'  => $expiresAt->toIso8601String(),
            'user'        => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [],
                'permissions' => method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name') : [],
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'id'               => $u->id,
            'name'             => $u->name,
            'email'            => $u->email,
            'roles'            => method_exists($u, 'getRoleNames') ? $u->getRoleNames() : [],
            'permissions'      => method_exists($u, 'getAllPermissions') ? $u->getAllPermissions()->pluck('name') : [],
            'abilities'        => $u->currentAccessToken()?->abilities ?? [],
            'token_expires_at' => optional($u->currentAccessToken()?->expires_at)->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     * Révoque le token courant et en émet un nouveau (2h).
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        // Révoquer le token courant
        $user->currentAccessToken()?->delete();

        $abilities = method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions()->pluck('name')->all()
            : ['*'];

        $expiresAt = now()->addHours(2);
        $newToken  = $user->createToken('api', $abilities, $expiresAt);

        // Remplir IP/UA si colonnes présentes
        try {
            $accessTokenModel = $newToken->accessToken;
            if ($accessTokenModel) {
                if (Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                    $accessTokenModel->ip_address = $request->ip();
                }
                if (Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                    $accessTokenModel->user_agent = substr((string)$request->userAgent(), 0, 255);
                }
                $accessTokenModel->save();
            }
        } catch (\Throwable $e) {
            // non bloquant
        }

        Log::channel('security')->info('token.refresh', [
            'user_id'    => $user->id,
            'ip'         => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'token'      => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        $user->currentAccessToken()?->delete();

        Log::channel('security')->info('logout.ok', [
            'user_id'    => $user->id,
            'email'      => $user->email,
            'ip'         => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 255),
        ]);

        return response()->json(['message' => 'Déconnecté.'], 200);
    }
}
