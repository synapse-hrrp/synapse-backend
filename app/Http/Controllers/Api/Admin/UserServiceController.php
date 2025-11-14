<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserServiceController extends Controller
{
    /**
     * POST /api/v1/admin/users/{user}/services
     * Body JSON possible:
     *  { "service_ids": [1,2,3] }
     *  ou
     *  { "services": [1,2,3] }
     */
    public function sync(Request $request, User $user)
    {
        $this->authorizeAction($request);

        $data = $request->validate([
            'service_ids'   => ['array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'services'      => ['array'],
            'services.*'    => ['integer', 'exists:services,id'],
        ]);

        $ids = collect($data['service_ids'] ?? $data['services'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // suppose une relation Many-to-Many: User->services()
        $user->services()->sync($ids);

        $user->load('services:id,name');

        return response()->json([
            'message' => 'Services synced',
            'data'    => [
                'id'          => $user->id,
                'service_ids' => $user->services->pluck('id')->map(fn($id)=>(int)$id)->values(),
                'services'    => $user->services->map(fn($s)=>[
                    'id'   => (int)$s->id,
                    'name' => $s->name,
                ])->values(),
            ],
        ]);
    }

    private function authorizeAction(Request $request)
    {
        $actor = $request->user();
        if (
            $actor &&
            (
                $actor->hasAnyRole(['admin','admin_caisse']) ||
                $actor->can('roles.assign')
            )
        ) {
            return;
        }
        abort(403, 'This action is unauthorized.');
    }
}
