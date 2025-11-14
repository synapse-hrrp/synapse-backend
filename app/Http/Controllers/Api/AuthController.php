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

        if (isset($user->is_active) && !$user->is_active) {
            Log::channel('security')->warning('login.blocked', [
                'user_id'    => $user->id,
                'email'      => $user->email,
                'ip'         => $request->ip(),
                'user_agent' => substr((string)$request->userAgent(), 0, 255),
            ]);
            return response()->json(['message' => 'Compte inactif.'], 403);
        }

        $isAdmin = method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['admin','dg','superuser']) : false; // ✅ inclut superuser

        if ($isAdmin) {
            $abilities = ['*'];
        } else {
            // Permissions Spatie -> abilities Sanctum (en lowercase)
            $abilities = method_exists($user, 'getAllPermissions')
                ? $user->getAllPermissions()
                    ->pluck('name')
                    ->map(fn($n) => Str::of($n)->lower()->value())
                    ->values()
                    ->all()
                : [];

            // ✅ Ajoute des alias CRUD pour éviter les 403 entre read/view/write/create/update/delete
            $abilities = $this->expandAbilities($abilities);

            // ✅ Assure les abilities Caisse attendues par tes middlewares
            $abilities = $this->ensureCaisseAbilities($abilities);

            // ✅ Si rôle caissier, rajoute le minimum vital caisse.*
            $abilities = $this->ensureCaisseAbilitiesFromRoles($user, $abilities);

            // ✅ Si rôle réception, force les abilities patients/visites + lookups
            $abilities = $this->ensureReceptionAbilitiesFromRoles($user, $abilities);

            // ✅ Lookups annexes si patients/visites (utile au front)
            $abilities = $this->ensureLookupsFromAbilities($abilities);
        }

        // Révoquer les anciens tokens (facultatif)
        try { $user->tokens()->delete(); } catch (\Throwable $e) {}

        $device    = $data['device_name'] ?? 'api';
        $expiresAt = now()->addHours(2);
        $newToken  = $user->createToken($device, $abilities, $expiresAt);

        // IP/UA si colonnes custom
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
        try {
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();
        } catch (\Throwable $e) {}

        // Charger le personnel minimal pour l’avatar dans le header
        $user->load([
            'personnel:id,user_id,first_name,last_name,avatar_path,service_id',
            'personnel.service:id,slug,name',
        ]);

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
                'abilities'   => $abilities,
                'personnel'   => $user->personnel ? [
                    'id'          => $user->personnel->id,
                    'first_name'  => $user->personnel->first_name,
                    'last_name'   => $user->personnel->last_name,
                    'avatar_path' => $user->personnel->avatar_path,
                    'service'     => $user->personnel->service ? [
                        'slug' => $user->personnel->service->slug,
                        'name' => $user->personnel->service->name,
                    ] : null,
                ] : null,
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request)
    {
        $u = $request->user();
        $u->load([
            'personnel:id,user_id,first_name,last_name,avatar_path,service_id',
            'personnel.service:id,slug,name',
        ]);

        return response()->json([
            'id'               => $u->id,
            'name'             => $u->name,
            'email'            => $u->email,
            'roles'            => method_exists($u, 'getRoleNames') ? $u->getRoleNames() : [],
            'permissions'      => method_exists($u, 'getAllPermissions') ? $u->getAllPermissions()->pluck('name') : [],
            'abilities'        => $u->currentAccessToken()?->abilities ?? [],
            'token_expires_at' => optional($u->currentAccessToken()?->expires_at)->toIso8601String(),
            'personnel'        => $u->personnel ? [
                'id'          => $u->personnel->id,
                'first_name'  => $u->personnel->first_name,
                'last_name'   => $u->personnel->last_name,
                'avatar_path' => $u->personnel->avatar_path,
                'service'     => $u->personnel->service ? [
                    'slug' => $u->personnel->service->slug,
                    'name' => $u->personnel->service->name,
                ] : null,
            ] : null,
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        $user->currentAccessToken()?->delete();

        $isAdmin = method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['admin','dg','superuser']) : false; // ✅

        if ($isAdmin) {
            $abilities = ['*'];
        } else {
            $abilities = method_exists($user, 'getAllPermissions')
                ? $user->getAllPermissions()
                    ->pluck('name')
                    ->map(fn($n) => Str::of($n)->lower()->value())
                    ->values()
                    ->all()
                : [];

            $abilities = $this->expandAbilities($abilities);
            $abilities = $this->ensureCaisseAbilities($abilities);
            $abilities = $this->ensureCaisseAbilitiesFromRoles($user, $abilities);   // ✅
            $abilities = $this->ensureReceptionAbilitiesFromRoles($user, $abilities); // ✅
            $abilities = $this->ensureLookupsFromAbilities($abilities);              // ✅
        }

        $expiresAt = now()->addHours(2);
        $newToken  = $user->createToken('api', $abilities, $expiresAt);

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
     * Ajoute des alias d’abilities pour éviter les 403
     */
    protected function expandAbilities(array $abilities): array
    {
        $set = collect($abilities)->map(fn($a) => Str::of($a)->lower()->value());

        $linkCrud = function (string $base) use ($set) {
            if ($set->contains("$base.view")) $set->push("$base.read");
            if ($set->contains("$base.read")) $set->push("$base.view");

            if ($set->contains("$base.write")) {
                $set->push("$base.create", "$base.update", "$base.delete");
            }
            if ($set->contains("$base.create") || $set->contains("$base.update") || $set->contains("$base.delete")) {
                $set->push("$base.write");
            }
        };

        foreach (['patients','visites','medecins','personnels','services','tarifs','examen','finance','users','roles'] as $mod) {
            $linkCrud($mod);
        }

        $synonyms = [
            'tarif'      => 'tarifs',
            'service'    => 'services',
            'medecin'    => 'medecins',
            'personnel'  => 'personnels',
            'examen'     => 'examens',
        ];
        foreach ($synonyms as $sing => $plu) {
            foreach (['read','view','write','create','update','delete'] as $act) {
                if ($set->contains("$sing.$act")) $set->push("$plu.$act");
                if ($set->contains("$plu.$act")) $set->push("$sing.$act");
            }
        }

        if ($set->contains('personnels.read') || $set->contains('personnels.view')) {
            $set->push('medecins.read','medecins.view');
        }
        if ($set->contains('medecins.read') || $set->contains('medecins.view')) {
            $set->push('personnels.read','personnels.view');
        }

        return $set->unique()->values()->all();
    }

    /**
     * ✅ S’assure que les abilities “caisse.*” attendues par les middlewares sont présentes.
     */
    protected function ensureCaisseAbilities(array $abilities): array
    {
        $set = collect($abilities)->map(fn($a) => Str::of($a)->lower()->value());

        $hasAnyCaisse = $set->first(fn($a) => str_starts_with($a, 'caisse.')) !== null;

        if ($hasAnyCaisse) {
            $set->push('caisse.access');

            if ($set->contains('caisse.reglement.create')) {
                $set->push('caisse.session.view', 'caisse.session.manage');
            }

            if ($set->contains('caisse.session.manage') && !$set->contains('caisse.session.view')) {
                $set->push('caisse.session.view');
            }
        }

        return $set->unique()->values()->all();
    }

    /**
     * ✅ Ajoute automatiquement les abilities caisse.* si l’utilisateur a le rôle caissier/cashier.
     */
    protected function ensureCaisseAbilitiesFromRoles(\App\Models\User $user, array $abilities): array
    {
        try {
            $isCashier = method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['caissier','cashier','caissier_service','caissier_general','admin_caisse']);
        } catch (\Throwable $e) {
            $isCashier = false;
        }

        if ($isCashier) {
            $abilities = array_merge($abilities, [
                'caisse.access',
                'caisse.session.view',
                'caisse.session.manage',
                'caisse.reglement.create',
            ]);
        }

        return collect($abilities)->map(fn($a) => strtolower($a))->unique()->values()->all();
    }

    /**
     * ✅ Si rôle réception, garantit patients/visites + lookups.
     */
    protected function ensureReceptionAbilitiesFromRoles(\App\Models\User $user, array $abilities): array
    {
        try {
            $isReception = method_exists($user, 'hasRole') && $user->hasRole('reception');
        } catch (\Throwable $e) {
            $isReception = false;
        }

        if ($isReception) {
            $abilities = array_merge($abilities, [
                'patients.view','patients.read','patients.create','patients.update', // créer/lire
                'visites.view','visites.read','visites.write',
                // lookups utiles au front d’accueil :
                'medecins.read','personnels.read','services.read','tarifs.read',
            ]);
        }

        return collect($abilities)->map(fn($a) => strtolower($a))->unique()->values()->all();
    }

    /**
     * ✅ Ajoute automatiquement les lookups si l’utilisateur a déjà patients.* ou visites.*
     */
    protected function ensureLookupsFromAbilities(array $abilities): array
    {
        $set = collect($abilities)->map(fn($a) => strtolower($a));

        $needsLookups =
            $set->contains('patients.view') || $set->contains('patients.read') ||
            $set->contains('visites.view')  || $set->contains('visites.read')  || $set->contains('visites.write');

        if ($needsLookups) {
            $set = $set->merge(['medecins.read','personnels.read','services.read','tarifs.read']);
        }

        return $set->unique()->values()->all();
    }
}
