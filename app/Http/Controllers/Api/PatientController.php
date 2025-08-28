<?php
// app/Http/Controllers/Api/PatientController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientStoreRequest;
use App\Http\Requests\PatientUpdateRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    // GET /api/patients  (recherche + pagination + tri)
    public function index(Request $request)
    {
        // Sanctum ability
        if (!$request->user()->tokenCan('*') && !$request->user()->tokenCan('patients.read')) {
            return response()->json(['message'=>'Forbidden: patients.read requis'], 403);
        }

        $q = $request->string('q')->toString();
        $sexe = $request->string('sexe')->toString();
        $groupe = $request->string('groupe_sanguin')->toString();
        $isActive = $request->filled('is_active') ? $request->boolean('is_active') : null;
        $deleted = $request->boolean('deleted', false);
        $sort = $request->string('sort', '-created_at')->toString();

        $query = Patient::query();
        if ($deleted) $query->withTrashed();

        if ($q !== '') {
            $query->where(function($b) use ($q) {
                $b->where('numero_dossier', 'like', "%$q%")
                  ->orWhere('nom', 'like', "%$q%")
                  ->orWhere('prenom', 'like', "%$q%")
                  ->orWhere('telephone', 'like', "%$q%");
            });
        }
        if ($sexe !== '') $query->where('sexe', $sexe);
        if ($groupe !== '') $query->where('groupe_sanguin', $groupe);
        if (!is_null($isActive)) $query->where('is_active', $isActive);

        $field = ltrim($sort, '-');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        if (!in_array($field, ['created_at','nom','prenom','numero_dossier','date_naissance'])) {
            $field = 'created_at';
        }
        $query->orderBy($field, $dir);

        $perPage = min(max((int)$request->get('limit', 20), 1), 200);
        $patients = $query->paginate($perPage);

        return PatientResource::collection($patients)->additional([
            'page' => $patients->currentPage(),
            'limit' => $patients->perPage(),
            'total' => $patients->total(),
        ]);
    }

    // POST /api/patients
    public function store(PatientStoreRequest $request)
    {
        if (!$request->user()->tokenCan('*') && !$request->user()->tokenCan('patients.write')) {
            return response()->json(['message'=>'Forbidden: patients.write requis'], 403);
        }

        $patient = Patient::create($request->validated());
        $this->audit($patient->id, 'create', $request->validated());

        return (new PatientResource($patient))->response()->setStatusCode(201);
    }

    // GET /api/patients/{patient}
    public function show(Request $request, Patient $patient)
    {
        if (!$request->user()->tokenCan('*') && !$request->user()->tokenCan('patients.read')) {
            return response()->json(['message'=>'Forbidden: patients.read requis'], 403);
        }

        return new PatientResource($patient);
    }

    // PATCH /api/patients/{patient}
    public function update(PatientUpdateRequest $request, Patient $patient)
    {
        if (!$request->user()->tokenCan('*') && !$request->user()->tokenCan('patients.write')) {
            return response()->json(['message'=>'Forbidden: patients.write requis'], 403);
        }

        $before = $patient->getRawOriginal();
        $patient->fill($request->validated())->save();

        $changes = $this->computeDiff($before, $patient->getRawOriginal());
        if (!empty($changes)) {
            $this->audit($patient->id, 'update', $changes);
        }

        return new PatientResource($patient);
    }

    // DELETE /api/patients/{patient} (soft delete)
    public function destroy(Request $request, Patient $patient)
    {
        if (!$request->user()->tokenCan('*') && !$request->user()->tokenCan('patients.write')) {
            return response()->json(['message'=>'Forbidden: patients.write requis'], 403);
        }

        $patient->delete();
        $this->audit($patient->id, 'delete', null);
        return response()->noContent();
    }

    // POST /api/patients/{id}/restore
    public function restore(Request $request, string $id)
    {
        if (!$request->user()->tokenCan('*') && !$request->user()->tokenCan('patients.write')) {
            return response()->json(['message'=>'Forbidden: patients.write requis'], 403);
        }

        $p = Patient::withTrashed()->findOrFail($id);
        if ($p->trashed()) {
            $p->restore();
            $this->audit($p->id, 'restore', null);
        }
        return new PatientResource($p);
    }

    // GET /api/patients/{id}/history
    public function history(Request $request, string $id)
    {
        if (!$request->user()->tokenCan('*') && !$request->user()->tokenCan('patients.audit')) {
            return response()->json(['message'=>'Forbidden: patients.audit requis'], 403);
        }

        $items = DB::table('patient_audits')->where('patient_id', $id)
            ->orderByDesc('created_at')->get();

        return response()->json($items);
    }

    // ── utilitaires audit ─────────────────────────────────────────────────────
    private function audit(string $patientId, string $action, $changes): void
    {
        DB::table('patient_audits')->insert([
            'patient_id' => $patientId,
            'action' => $action,
            'changes' => $changes ? json_encode($changes) : null,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    private function computeDiff(array $before, array $after): array
    {
        $ignore = ['updated_at','created_at','deleted_at'];
        $diff = [];
        foreach ($after as $k => $v) {
            if (in_array($k, $ignore)) continue;
            $old = $before[$k] ?? null;
            if ($old != $v) $diff[$k] = ['from' => $old, 'to' => $v];
        }
        return $diff;
    }
}
