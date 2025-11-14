<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RendezVousResource;
use App\Models\RendezVous;
use App\Models\Medecin;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RendezVousController extends Controller
{
    /**
     * GET /api/v1/rendez-vous
     * Liste globale des RDV (avec filtres optionnels).
     */
    public function index(Request $r)
    {
        $q = RendezVous::query()
            ->with(['medecin.personnel', 'patient', 'service']);

        // Filtres
        if ($r->filled('medecin_id')) {
            $q->where('medecin_id', $r->input('medecin_id'));
        }

        if ($r->filled('patient_id')) {
            $q->where('patient_id', $r->input('patient_id'));
        }

        if ($r->filled('service_slug')) {
            $q->where('service_slug', $r->input('service_slug'));
        }

        if ($r->filled('status')) {
            $q->where('status', $r->input('status'));
        }

        if ($r->filled('date')) {
            $q->whereDate('date', $r->input('date'));
        } else {
            if ($r->filled('from')) {
                $q->whereDate('date', '>=', $r->input('from'));
            }
            if ($r->filled('to')) {
                $q->whereDate('date', '<=', $r->input('to'));
            }
        }

        $q->orderBy('date')->orderBy('start_time');

        // Pagination
        $rdvs = $q->paginate(20)->withQueryString();

        return RendezVousResource::collection($rdvs);
    }

    /**
     * GET /api/v1/medecins/{medecin}/rendez-vous
     * RDV d’un médecin spécifique.
     */
    public function listByMedecin(Request $r, Medecin $medecin)
    {
        $q = $medecin->rendezVous()
            ->with(['patient','service']);

        if ($r->filled('date')) {
            $q->whereDate('date', $r->input('date'));
        }

        if ($r->filled('status')) {
            $q->where('status', $r->input('status'));
        }

        $q->orderBy('date')->orderBy('start_time');

        // Tu peux mettre ->get() si tu ne veux pas de pagination
        $rdvs = $q->paginate(20)->withQueryString();

        return RendezVousResource::collection($rdvs);
    }

    /**
     * POST /api/v1/rendez-vous
     * Création d’un RDV en respectant planning + exceptions + capacité.
     */
    public function store(Request $r)
    {
        $data = $r->validate([
            'medecin_id'   => ['required','exists:medecins,id'],
            'patient_id'   => ['required','exists:patients,id'],
            'service_slug' => ['required','exists:services,slug'],
            'tarif_id'     => ['nullable','integer','exists:tarifs,id'],
            'date'         => ['required','date'],
            'start_time'   => ['required','date_format:H:i'],
        ]);

        // 1) Vérifier service actif et lié au médecin
        $medecin = Medecin::with([
            'services' => fn($q) => $q->where('services.slug', $data['service_slug'])
        ])->findOrFail($data['medecin_id']);

        $pivot   = $medecin->services->first();
        $service = Service::active()->slug($data['service_slug'])->first();

        if (!$service || !$pivot || !$pivot->pivot->is_active) {
            return response()->json(['message' => 'Service non disponible pour ce médecin'], 422);
        }

        $date    = Carbon::parse($data['date'])->toDateString();
        $weekday = Carbon::parse($date)->isoWeekday(); // 1 (lundi) → 7 (dimanche)

        // 2) Récupérer planning hebdo + éventuelle exception ce jour-là
        $planning = DB::table('medecin_plannings')
            ->where('medecin_id', $data['medecin_id'])
            ->where('weekday', $weekday)
            ->first();

        $exception = DB::table('medecin_planning_exceptions')
            ->where('medecin_id', $data['medecin_id'])
            ->whereDate('date', $date)
            ->first();

        // 3) Règles: exception > pivot > planning
        $slotDuration = 20;
        $capacity     = 1;
        $startWindow  = null;
        $endWindow    = null;

        if ($exception) {
            if (!$exception->is_working) {
                return response()->json(['message' => 'Médecin fermé ce jour'], 422);
            }

            // ⚠️ PROTECTION si $planning est null : on passe par des variables
            $planningSlot = $planning ? $planning->slot_duration : null;
            $planningCap  = $planning ? $planning->capacity_per_slot : null;

            $slotDuration = $exception->slot_duration
                ?? ($pivot->pivot->slot_duration ?? ($planningSlot ?? 20));

            $capacity = $exception->capacity_per_slot
                ?? ($pivot->pivot->capacity_per_slot ?? ($planningCap ?? 1));

            $startWindow = $exception->start_time;
            $endWindow   = $exception->end_time;
        } elseif ($planning) {
            $slotDuration = $pivot->pivot->slot_duration
                ?? ($planning->slot_duration ?? 20);

            $capacity = $pivot->pivot->capacity_per_slot
                ?? ($planning->capacity_per_slot ?? 1);

            $startWindow = $planning->start_time;
            $endWindow   = $planning->end_time;
        } else {
            return response()->json(['message' => 'Aucun horaire défini pour ce jour'], 422);
        }

        // 4) Vérifs alignement / fenêtre
        $start       = Carbon::parse("$date {$data['start_time']}");
        $windowStart = Carbon::parse("$date $startWindow");
        $windowEnd   = Carbon::parse("$date $endWindow");

        // Start/end dans la fenêtre
        if (!($start >= $windowStart && $start->copy()->addMinutes($slotDuration) <= $windowEnd)) {
            return response()->json(['message' => 'Horaire hors fenêtre'], 422);
        }

        // Alignement sur les créneaux
        $minutesFromStart = $windowStart->diffInMinutes($start);
        if ($minutesFromStart % $slotDuration !== 0) {
            return response()->json(['message' => 'Heure non alignée sur les créneaux'], 422);
        }

        // 5) Capacité globale du créneau (tous services confondus)
        $taken = RendezVous::where([
                'medecin_id' => $data['medecin_id'],
                'date'       => $date,
                'start_time' => $start->format('H:i:s'),
            ])
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($taken >= $capacity) {
            return response()->json(['message' => 'Créneau complet'], 409);
        }

        // 6) Statut auto (règle ≥5 patients/jour)
        $dayCount = RendezVous::where('medecin_id', $data['medecin_id'])
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->count();

        $status = ($dayCount + 1) >= 5 ? 'confirmed' : 'pending';

        $end = $start->copy()->addMinutes($slotDuration);

        $rdv = RendezVous::create([
            'medecin_id'   => $data['medecin_id'],
            'patient_id'   => $data['patient_id'],
            'service_slug' => $data['service_slug'],
            'tarif_id'     => $data['tarif_id'] ?? null,
            'date'         => $date,
            'start_time'   => $start->format('H:i'),
            'end_time'     => $end->format('H:i'),
            'status'       => $status,
        ]);

        return (new RendezVousResource(
            $rdv->load(['medecin.personnel', 'patient', 'service'])
        ))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/rendez-vous/{rdv}
     * Mise à jour du statut (ou annulation) d’un RDV.
     */
    public function update(Request $r, RendezVous $rdv)
    {
        $data = $r->validate([
            'status'        => ['required', Rule::in(['pending','confirmed','cancelled','noshow','done'])],
            'cancel_reason' => ['nullable','string','max:500'],
        ]);

        $rdv->update($data);

        return new RendezVousResource(
            $rdv->fresh()->load(['medecin.personnel', 'patient', 'service'])
        );
    }
}
