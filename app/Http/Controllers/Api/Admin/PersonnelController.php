<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $perPage  = max(1, min((int) $request->query('per_page', 10), 100));

        $query = Personnel::query()->with(['user:id,name,email', 'service:id,name']);

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
        $personnel = Personnel::with(['user:id,name,email', 'service:id,name'])
            ->where('user_id', $user_id)
            ->firstOrFail();

        return response()->json($personnel);
    }

    /**
     * POST /api/v1/admin/personnels
     */
    public function store(Request $request)
    {
        $data = $request->validate([
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
            'avatar'        => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ]);

        $p = Personnel::create($data);

        // upload avatar
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('personnels/avatars', 'public');
            $p->forceFill(['avatar_path' => $path])->save();
        }

        return response()->json([
            'message' => 'Personnel créé',
            'data'    => $p->load(['user:id,name,email','service:id,name']),
        ], 201);
    }

    /**
     * GET /api/v1/admin/personnels/{personnel}
     */
    public function show(Personnel $personnel)
    {
        return response()->json(
            $personnel->load(['user:id,name,email','service:id,name'])
        );
    }

    /**
     * PATCH /api/v1/admin/personnels/{personnel}
     */
    public function update(Request $request, Personnel $personnel)
    {
        $data = $request->validate([
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
            'avatar'        => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'remove_avatar' => ['sometimes','boolean'],
        ]);

        // mise à jour des champs simples
        $personnel->update($data);

        // suppression avatar
        if ($request->boolean('remove_avatar') && $personnel->avatar_path) {
            Storage::disk('public')->delete($personnel->avatar_path);
            $personnel->update(['avatar_path' => null]);
        }

        // upload avatar
        if ($request->hasFile('avatar')) {
            if ($personnel->avatar_path) {
                Storage::disk('public')->delete($personnel->avatar_path);
            }
            $path = $request->file('avatar')->store('personnels/avatars', 'public');
            $personnel->update(['avatar_path' => $path]);
        }

        return response()->json([
            'message' => 'Personnel modifié',
            'data'    => $personnel->fresh()->load(['user:id,name,email','service:id,name']),
        ]);
    }

    /**
     * DELETE /api/v1/admin/personnels/{personnel}
     */
    public function destroy(Personnel $personnel)
    {
        if ($personnel->avatar_path) {
            Storage::disk('public')->delete($personnel->avatar_path);
        }
        $personnel->delete();

        return response()->json(['message' => 'Personnel supprimé']);
    }

    /**
     * POST /api/v1/admin/personnels/{personnel}/avatar
     * Upload/remplacement dédié (multipart form-data: avatar=File)
     */
    public function uploadAvatar(Request $request, Personnel $personnel)
    {
        $request->validate([
            'avatar' => ['required','file','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ]);

        if ($personnel->avatar_path) {
            Storage::disk('public')->delete($personnel->avatar_path);
        }

        $path = $request->file('avatar')->store('personnels/avatars', 'public');
        $personnel->forceFill(['avatar_path' => $path])->save();

        return response()->json([
            'message' => 'Avatar mis à jour',
            'data'    => $personnel->fresh()->load(['user:id,name,email','service:id,name']),
        ]);
    }

    /**
     * DELETE /api/v1/admin/personnels/{personnel}/avatar
     * Suppression dédiée
     */
    public function deleteAvatar(Personnel $personnel)
    {
        if ($personnel->avatar_path) {
            Storage::disk('public')->delete($personnel->avatar_path);
        }
        $personnel->forceFill(['avatar_path' => null])->save();

        return response()->json([
            'message' => 'Avatar supprimé',
            'data'    => $personnel->fresh()->load(['user:id,name,email','service:id,name']),
        ]);
    }
}
