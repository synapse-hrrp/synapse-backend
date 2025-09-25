<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        // ✅ Admin/DG = wildcard, sinon on mappe les permissions Spatie → abilities Sanctum (+ alias)
        $isAdmin = method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['admin','dg']) : false;

        if ($isAdmin) {
            $abilities = ['*'];
        } else {
            $abilities = method_exists($user, 'getAllPermissions')
                ? $user->getAllPermissions()
                    ->pluck('name')
                    ->map(fn($n) => Str::of($n)->lower()->value())
                    ->values()
                    ->all()
                : ['*']; // fallback si Spatie absent

            $abilities = $this->expandAbilities($abilities);
        }

        // (optionnel mais recommandé) révoquer anciens tokens de ce device/user
        try { $user->tokens()->delete(); } catch (\Throwable $e) {}

        $device    = $data['device_name'] ?? 'api';
        $expiresAt = now()->addHours(2);
        $newToken  = $user->createToken($device, $abilities, $expiresAt);

        // Renseigne IP/UA si colonnes présentes
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
        } catch (\Throwable $e) {}

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

        $isAdmin = method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['admin','dg']) : false;

        if ($isAdmin) {
            $abilities = ['*'];
        } else {
            $abilities = method_exists($user, 'getAllPermissions')
                ? $user->getAllPermissions()
                    ->pluck('name')
                    ->map(fn($n) => Str::of($n)->lower()->value())
                    ->values()
                    ->all()
                : ['*'];

            $abilities = $this->expandAbilities($abilities);
        }

        $expiresAt = now()->addHours(2);
        $newToken  = $user->createToken('api', $abilities, $expiresAt);

        // IP/UA si colonnes
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
        } catch (\Throwable $e) {}

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

    /**
     * Ajoute des alias d’abilities pour éviter les 403 "read vs view", "write vs update"
     */
    protected function expandAbilities(array $abilities): array
    {
        $set = collect($abilities)->map(fn($a) => Str::of($a)->lower()->value());

        // patients
        if ($set->contains('patients.view'))   $set->push('patients.read');
        if ($set->contains('patients.read'))   $set->push('patients.view');
        if ($set->contains('patients.update')) $set->push('patients.write');
        if ($set->contains('patients.write'))  $set->push('patients.update');
        if ($set->contains('patients.delete')) $set->push('patients.destroy'); // au cas où

        // visites
        if ($set->contains('visites.view'))    $set->push('visites.read');
        if ($set->contains('visites.read'))    $set->push('visites.view');
        if ($set->contains('visites.update'))  $set->push('visites.write');
        if ($set->contains('visites.write'))   $set->push('visites.update');

        // ajoute ici d’autres modules si besoin (labo, finance, etc.)

        return $set->unique()->values()->all();
    }
}