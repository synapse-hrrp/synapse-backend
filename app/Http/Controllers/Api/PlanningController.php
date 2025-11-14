<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medecin;
use App\Models\MedecinPlanning;
use App\Models\MedecinPlanningException;
use App\Services\DisponibiliteService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PlanningController extends Controller
{
    public function index(Medecin $medecin) {
        return [
            'recurrents' => $medecin->plannings()->orderBy('weekday')->get(),
            'exceptions' => $medecin->planningExceptions()->orderBy('date','desc')->limit(60)->get(),
        ];
    }

    public function store(Request $r, Medecin $medecin) {
        $data = $r->validate([
            'segments' => ['required','array','min:1'],
            'segments.*.weekday' => ['required','integer','between:1,7'],
            'segments.*.start_time' => ['required','date_format:H:i'],
            'segments.*.end_time'   => ['required','date_format:H:i','after:segments.*.start_time'],
            'segments.*.slot_duration' => ['nullable','integer','between:5,180'],
            'segments.*.capacity_per_slot' => ['nullable','integer','between:1,20'],
            'segments.*.is_active' => ['nullable','boolean'],
        ]);

        // stratégie simple: remplacement complet
        $medecin->plannings()->delete();
        foreach ($data['segments'] as $seg) {
            $medecin->plannings()->create($seg);
        }
        return response()->json(['ok'=>true]);
    }

    public function addException(Request $r, Medecin $medecin) {
        $data = $r->validate([
            'date' => ['required','date'],
            'is_working' => ['required','boolean'],
            'start_time' => ['nullable','date_format:H:i'],
            'end_time'   => ['nullable','date_format:H:i','after:start_time'],
            'slot_duration' => ['nullable','integer','between:5,180'],
            'capacity_per_slot' => ['nullable','integer','between:1,20'],
            'reason' => ['nullable','string','max:500'],
        ]);

        $ex = $medecin->planningExceptions()->updateOrCreate(
            ['date'=>$data['date']],
            $data
        );
        return response()->json($ex, 201);
    }

    public function disponibilites(Request $r, Medecin $medecin, DisponibiliteService $svc) {
        $from = Carbon::parse($r->query('from', now()->toDateString()));
        $to   = Carbon::parse($r->query('to',   now()->addWeeks(4)->toDateString()));
        $serviceSlug = $r->query('service_slug');

        if ($serviceSlug) {
            $r->validate(['service_slug'=>['string','exists:services,slug']]);
        }

        $res = $svc->slots($medecin, $from, $to, $serviceSlug);
        return response()->json($res);
    }
}
