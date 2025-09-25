<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedecineStoreRequest;
use App\Http\Requests\MedecineUpdateRequest;
use App\Http\Resources\MedecineResource;
use App\Models\Medecine;
use App\Models\Visite;
use Illuminate\Http\Request;

class MedecineController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('medecine.view'))) {
            return response()->json(['message' => 'Forbidden: medecine.view requis'], 403);
        }

        $q          = (string) $request->query('q', '');
        $statut     = (string) $request->query('statut', '');
        $patient_id = (string) $request->query('patient_id', '');
        $visite_id  = (string) $request->query('visite_id', '');
        $soignant   = (string) $request->query('soignant_id', '');
        $date_from  = (string) $request->query('date_from', ''); // YYYY-MM-DD
        $date_to    = (string) $request->query('date_to',   ''); // YYYY-MM-DD

        $query = Medecine::query()->with([
            'patient',
            'visite',
            // ⚠️ soignant = Personnel
            'soignant:id,first_name,last_name,job_title,service_id'
        ]);

        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('motif','like',"%$q%")
                  ->orWhere('diagnostic','like',"%$q%")
                  ->orWhere('examen_clinique','like',"%$q%")
                  ->orWhere('traitements','like',"%$q%")
                  ->orWhere('observation','like',"%$q%");
            });
        }
        if ($statut     !== '') $query->where('statut', $statut);
        if ($patient_id !== '') $query->where('patient_id', $patient_id);
        if ($visite_id  !== '') $query->where('visite_id', $visite_id);
        if ($soignant   !== '') $query->where('soignant_id', $soignant);
        if ($date_from  !== '') $query->whereDate('date_acte', '>=', $date_from);
        if ($date_to    !== '') $query->whereDate('date_acte', '<=', $date_to);

        $sort  = (string) $request->query('sort','-date_acte');
        $field = ltrim($sort,'-');
        $dir   = str_starts_with($sort,'-') ? 'desc' : 'asc';
        if (!in_array($field, ['date_acte','created_at','motif','statut'], true)) {
            $field = 'date_acte';
        }
        $query->orderBy($field,$dir)->orderByDesc('created_at');

        $perPage = min(max((int)$request->get('limit',20),1),200);
        $items = $query->paginate($perPage);

        return MedecineResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    public function store(MedecineStoreRequest $request)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('medecine.create'))) {
            return response()->json(['message' => 'Forbidden: medecine.create requis'], 403);
        }

        $data = $request->validated();

        // ❌ ne jamais accepter soignant_id du client
        unset($data['soignant_id']);

        // Déduire la visite si absente (dernier passage du patient)
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // Imposer soignant/service/patient depuis la visite
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                $data['soignant_id'] = $v->medecin_id; // ⚠️ médecin = Personnel
                // si tu as la colonne service_id dans medecines, tu peux faire:
                // $data['service_id']  = $data['service_id'] ?? $v->service_id;
            }
        }

        if (empty($data['soignant_id'])) {
            return response()->json([
                'message' => "Impossible de créer : aucun médecin (Personnel) n'est associé à la visite."
            ], 422);
        }

        // Valeurs par défaut
        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'en_cours';

        // Idempotence : si déjà créé (via Observer) pour cette visite, on met à jour
        $item = !empty($data['visite_id'])
            ? Medecine::where('visite_id', $data['visite_id'])->first()
            : null;

        if ($item) {
            $item->fill($data)->save();
        } else {
            $item = Medecine::create($data);
        }
        // après avoir résolu/déduit visite_id
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                $data['soignant_id'] = $v->medecin_id;
                $data['service_id']  = $data['service_id'] ?? $v->service_id; // ✅
            }
        }

        return (new MedecineResource(
            $item->load(['patient','visite','soignant:id,first_name,last_name,job_title,service_id'])
        ))->response()->setStatusCode(201);
    }

    public function show(Request $request, Medecine $medecine)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('medecine.view'))) {
            return response()->json(['message' => 'Forbidden: medecine.view requis'], 403);
        }

        return new MedecineResource(
            $medecine->load(['patient','visite','soignant:id,first_name,last_name,job_title,service_id'])
        );
    }

    public function update(MedecineUpdateRequest $request, Medecine $medecine)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('medecine.update'))) {
            return response()->json(['message' => 'Forbidden: medecine.update requis'], 403);
        }

        $data = $request->validated();
        unset($data['soignant_id']); // verrou : toujours déduit de la visite

        // Si on change (ou déduit) la visite, réimposer le médecin
        if (array_key_exists('visite_id', $data) && !empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['soignant_id'] = $v->medecin_id;
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                // $data['service_id']  = $data['service_id'] ?? $v->service_id; // si colonne
            }
        }

        // Dernière sécurité : si rien en entrée et que le modèle n’a pas déjà un soignant, bloquer
        if (empty($data['soignant_id']) && empty($medecine->soignant_id)) {
            return response()->json([
                'message' => "Impossible de mettre à jour : aucun médecin (Personnel) n'est associé à la visite."
            ], 422);
        }

        $medecine->fill($data)->save();

        if (array_key_exists('visite_id', $data) && !empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['soignant_id'] = $v->medecin_id;
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                $data['service_id']  = $data['service_id'] ?? $v->service_id; // ✅
            }
        }


        return new MedecineResource(
            $medecine->load(['patient','visite','soignant:id,first_name,last_name,job_title,service_id'])
        );
    }

    public function destroy(Request $request, Medecine $medecine)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('medecine.delete'))) {
            return response()->json(['message' => 'Forbidden: medecine.delete requis'], 403);
        }

        $medecine->delete();
        return response()->noContent();
    }
}
