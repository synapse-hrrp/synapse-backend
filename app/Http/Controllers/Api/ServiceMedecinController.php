<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Http\Request;

class ServiceMedecinController extends Controller
{
    public function index(Request $request, int $service)
    {
        $perPage = $request->integer('per_page', 50);

        $medecins = Personnel::query()
            ->select(['id','first_name','last_name','job_title','service_id'])
            ->medecins()
            ->where('service_id', $service)
            ->orderBy('last_name')
            ->paginate($perPage);

        $medecins->getCollection()->transform(function($p) {
            $p->full_name = trim(($p->first_name ?? '').' '.($p->last_name ?? ''));
            return $p;
        });

        return response()->json($medecins);
    }
}
