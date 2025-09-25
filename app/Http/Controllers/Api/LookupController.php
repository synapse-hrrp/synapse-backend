<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * GET /api/v1/lookups/medecins
     * Params:
     *  - q          : recherche (nom/email/personnel.{first,last}_name/matricule)
     *  - per_page   : pagination (def=20)
     *  - service_id : filtre optionnel (ne renvoie que les médecins rattachés à ce service via personnel.service_id)
     *  - active     : 1/0 pour filtrer les users actifs (def: 1)
     *  - mode=options : renvoie un tableau plat pour <select>
     */
    public function medecins(Request $request)
    {
        $q         = trim((string) $request->query('q', ''));
        $perPage   = max(1, min((int) $request->query('per_page', 20), 100));
        $serviceId = $request->integer('service_id');
        $active    = $request->has('active')
                        ? filter_var($request->query('active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                        : true; // par défaut: actifs uniquement

        $query = User::role('medecin')
            ->with(['personnel:id,user_id,first_name,last_name,matricule,service_id', 'personnel.service:id,name'])
            ->select('id','name','email','phone','is_active','created_at');

        if (!is_null($active)) {
            $query->where('is_active', $active);
        }

        if ($serviceId) {
            $query->whereHas('personnel', fn($p) => $p->where('service_id', $serviceId));
        }

        if ($q !== '') {
            $like = '%' . preg_replace('/\s+/', '%', $q) . '%';
            $query->where(function($x) use ($like) {
                $x->where('name','like',$like)
                  ->orWhere('email','like',$like)
                  ->orWhereHas('personnel', function($p) use ($like){
                      $p->where('first_name','like',$like)
                        ->orWhere('last_name','like',$like)
                        ->orWhere('matricule','like',$like);
                  });
            });
        }

        // ---- MODE OPTIONS: tableau plat pour <select> ----
        if ($request->query('mode') === 'options') {
            $limit = (int) $request->query('limit', 100);
            $items = $query->limit($limit)->get()->map(function (User $u) {
                $fullName = $u->personnel
                    ? trim(($u->personnel->first_name ?? '') . ' ' . ($u->personnel->last_name ?? ''))
                    : ($u->name ?? '');
                $svc = $u->personnel?->service;

                return [
                    'id'           => $u->id,                         // à envoyer au back (medecin_id)
                    'label'        => $fullName !== '' ? $fullName : $u->email, // affiché dans le select
                    'email'        => $u->email,
                    'matricule'    => $u->personnel->matricule ?? null,
                    'service_id'   => $u->personnel->service_id ?? null,
                    'service_name' => $svc?->name,
                ];
            })->values();

            return response()->json(['options' => $items]);
        }

        // ---- Mode paginé (liste détaillée) ----
        $page = $query->paginate($perPage);
        $page->through(function (User $u) {
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone,
                'is_active'  => $u->is_active,
                'created_at' => $u->created_at,
                'personnel'  => $u->personnel ? [
                    'first_name' => $u->personnel->first_name,
                    'last_name'  => $u->personnel->last_name,
                    'matricule'  => $u->personnel->matricule,
                    'service'    => $u->personnel->service ? [
                        'id'   => $u->personnel->service->id,
                        'name' => $u->personnel->service->name,
                    ] : null,
                ] : null,
            ];
        });

        return response()->json($page);
    }
}
