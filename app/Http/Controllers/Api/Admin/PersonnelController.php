<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PersonnelController extends Controller
{
    /**
     * GET /api/v1/admin/personnels
     */
    public function index(Request $request)
    {
        $userId   = $request->query('user_id');
        $service  = $request->query('service_id');
        $q        = trim((string) $request->query('q', ''));
        $perPage  = (int) $request->query('per_page', 10);
        $perPage  = max(1, min($perPage, 100));

        $query = Personnel::query()->with(['user:id,name,email', 'service:id,name,slug']);

        if (!empty($userId))   $query->where('user_id', $userId);
        if (!empty($service))  $query->where('service_id', $service);

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

        return response()->json(
            $query->orderByDesc('created_at')->paginate($perPage)
        );
    }

    /**
     * GET /api/v1/admin/personnels/by-user/{user_id}
     */
    public function byUser(int $user_id)
    {
        $p = Personnel::with(['user:id,name,email', 'service:id,name,slug'])
            ->where('user_id', $user_id)
            ->firstOrFail();

        return response()->json(['data' => $p]);
    }

    /**
     * POST /api/v1/admin/personnels
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
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
            'roles'         => ['sometimes','array'],
            'roles.*'       => ['string','exists:roles,name'],
            'permissions'   => ['sometimes','array'],
            'permissions.*' => ['string','exists:permissions,name'],
        ]);

        // Séparer roles/permissions envoyés par le frontend
        $roles       = $validated['roles'] ?? [];
        $permissions = $validated['permissions'] ?? [];
        unset($validated['roles'], $validated['permissions']);

        $p = Personnel::create($validated);

        // Attribuer le rôle automatique selon le service si roles non fournis
        $serviceRoleMap = [
            'laboratoire'    => 'laborantin',
            'pharmacie'      => 'pharmacien',
            'finance'        => 'caissier',
            'consultations'  => 'medecin',
            'accueil'        => 'reception',
            'pansement'      => 'infirmier',
            'gestion-malade' => 'gestionnaire',
        ];

        $user = $p->user;
        if ($user) {
            if (!empty($roles)) {
                $user->syncRoles($roles);
            } elseif ($p->service && isset($serviceRoleMap[$p->service->slug])) {
                $user->syncRoles([$serviceRoleMap[$p->service->slug]]);
            }

            if (!empty($permissions)) {
                $user->syncPermissions($permissions);
            }
        }

        return response()->json([
            'message' => 'Personnel créé',
            'data'    => $p->load(['user:id,name,email','service:id,name,slug']),
        ], 201);
    }

    /**
     * PATCH /api/v1/admin/personnels/{personnel}
     */
    public function update(Request $request, Personnel $personnel)
    {
        $payload = $request->all();
        if (array_key_exists('phone', $payload)) {
            $payload['phone_alt'] = $payload['phone'];
            unset($payload['phone']);
        }
        if (array_key_exists('hire_date', $payload)) {
            $payload['hired_at'] = $payload['hire_date'];
            unset($payload['hire_date']);
        }
        unset($payload['user_id']);

        $validated = validator($payload, [
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
            'roles'         => ['sometimes','array'],
            'roles.*'       => ['string','exists:roles,name'],
            'permissions'   => ['sometimes','array'],
            'permissions.*' => ['string','exists:permissions,name'],
            // autoriser la mise à jour via PATCH si tu envoies avatar_path depuis le front
            'avatar_path'   => ['sometimes','nullable','string','max:255'],
        ])->validate();

        $roles       = $validated['roles'] ?? null;
        $permissions = $validated['permissions'] ?? null;
        unset($validated['roles'], $validated['permissions']);

        $personnel->update($validated);

        $serviceRoleMap = [
            'laboratoire'    => 'laborantin',
            'pharmacie'      => 'pharmacien',
            'finance'        => 'caissier',
            'consultations'  => 'medecin',
            'reception'      => 'reception',
            'pansement'      => 'infirmier',
            'gestion-malade' => 'gestionnaire',
        ];

        $user = $personnel->user;
        if ($user) {
            if ($roles !== null) {
                $user->syncRoles($roles);
            } elseif ($personnel->service && isset($serviceRoleMap[$personnel->service->slug])) {
                $user->syncRoles([$serviceRoleMap[$personnel->service->slug]]);
            }

            if ($permissions !== null) {
                $user->syncPermissions($permissions);
            }
        }

        return response()->json([
            'message' => 'Personnel modifié',
            'data'    => $personnel->load(['user:id,name,email','service:id,name,slug']),
        ]);
    }

    /**
     * GET /api/v1/admin/personnels/{personnel}
     */
    public function show(Personnel $personnel)
    {
        return response()->json(
            $personnel->load(['user:id,name,email', 'service:id,name,slug'])
        );
    }

    /**
     * DELETE /api/v1/admin/personnels/{personnel}
     */
    public function destroy(Personnel $personnel)
    {
        $personnel->delete();

        return response()->json([
            'message' => 'Personnel supprimé avec succès'
        ]);
    }

    /**
     * POST /api/v1/admin/personnels/{personnel}/avatar
     * Champ attendu: 'file' (image)
     */
    public function uploadAvatar(Request $request, Personnel $personnel)
    {
        try {
            $data = $request->validate([
                'file' => ['required','file','image','mimes:jpg,jpeg,png,webp','max:5120'], // 5MB
            ]);

            // Choix du disque
            $disk = config('filesystems.default', 'public');
            if (!in_array($disk, ['public','s3'])) {
                $disk = 'public';
            }

            $folder = 'avatars/personnels/'.$personnel->id;
            $path = Storage::disk($disk)->putFile($folder, $data['file']); // ex: avatars/personnels/22/xxx.jpg
            if (!$path) {
                return response()->json(['message' => 'Impossible de stocker le fichier'], 500);
            }

            $isS3 = $disk === 's3';
            $publicUrl = $isS3
                ? Storage::disk('s3')->url($path)
                : url('/storage/'.ltrim($path,'/'));

            // Sauvegarder le chemin (préférer un chemin « utilisable » par le front)
            $personnel->avatar_path = $isS3 ? $publicUrl : '/storage/'.ltrim($path,'/');
            $personnel->save();

            return response()->json([
                'ok'          => true,
                'avatar_path' => $personnel->avatar_path, // ex: /storage/avatars/personnels/22/xxx.jpg
                'avatar_url'  => $publicUrl,              // URL absolue
            ], 201);

        } catch (ValidationException $ve) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Upload avatar failed', [
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Upload avatar failed'], 500);
        }
    }

    /**
     * DELETE /api/v1/admin/personnels/{personnel}/avatar
     */
    public function deleteAvatar(Personnel $personnel)
    {
        try {
            if ($personnel->avatar_path) {
                // si c'est /storage/xxx -> supprimer le fichier physique du disque 'public'
                if (str_starts_with($personnel->avatar_path, '/storage/')) {
                    $disk = 'public';
                    $relative = ltrim(substr($personnel->avatar_path, strlen('/storage/')), '/'); // avatars/...
                    if (Storage::disk($disk)->exists($relative)) {
                        Storage::disk($disk)->delete($relative);
                    }
                }
            }

            $personnel->avatar_path = null;
            $personnel->save();

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            \Log::error('Delete avatar failed', [
                'personnel_id' => $personnel->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Delete avatar failed'], 500);
        }
    }
}
