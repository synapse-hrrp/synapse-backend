<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaboratoireStoreRequest;
use App\Http\Requests\LaboratoireUpdateRequest;

use App\Http\Resources\LaboratoireResource;
use App\Models\Laboratoire;
use Illuminate\Http\Request;

class LaboratoireController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('labo.view'))) {
            return response()->json(['message' => 'Forbidden: labo.view requis'], 403);
        }

        $q       = $request->string('q')->toString();
        $status  = $request->string('status')->toString();
        $patient = $request->string('patient_id')->toString();
        $test    = $request->string('test_code')->toString();

        $query = Laboratoire::query();

        if ($q !== '') {
            $query->where(function($b) use ($q) {
                $b->where('test_name','like',"%$q%")
                  ->orWhere('test_code','like',"%$q%")
                  ->orWhere('specimen','like',"%$q%");
            });
        }
        if ($status !== '') $query->where('status',$status);
        if ($patient !== '') $query->where('patient_id',$patient);
        if ($test !== '') $query->where('test_code',$test);

        $sort  = $request->string('sort','-requested_at')->toString();
        $field = ltrim($sort,'-');
        $dir   = str_starts_with($sort,'-') ? 'desc' : 'asc';
        if (!in_array($field, ['requested_at','validated_at','created_at','test_name','status'])) {
            $field = 'requested_at';
        }
        $query->orderBy($field,$dir);

        $perPage = min(max((int)$request->get('limit',20),1),200);
        $items = $query->paginate($perPage);

        return LaboratoireResource::collection($items)->additional([
            'page' => $items->currentPage(),
            'limit'=> $items->perPage(),
            'total'=> $items->total(),
        ]);
    }

    public function store(LaboratoireStoreRequest $request)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('labo.request.create'))) {
            return response()->json(['message' => 'Forbidden: labo.request.create requis'], 403);
        }

        $data = $request->validated();
        $data['requested_by'] = $u->id ?? null;
        $item = Laboratoire::create($data);

        return (new LaboratoireResource($item))->response()->setStatusCode(201);
    }

    public function show(Request $request, Laboratoire $laboratoire)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('labo.view'))) {
            return response()->json(['message' => 'Forbidden: labo.view requis'], 403);
        }
        return new LaboratoireResource($laboratoire);
    }

    public function update(LaboratoireUpdateRequest $request, Laboratoire $laboratoire)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('labo.result.write'))) {
            return response()->json(['message' => 'Forbidden: labo.result.write requis'], 403);
        }

        $laboratoire->fill($request->validated());

        if ($request->string('status')->toString() === 'validated') {
            $laboratoire->validated_by = $u->id ?? null;
            $laboratoire->validated_at = now();
        }

        $laboratoire->save();

        return new LaboratoireResource($laboratoire);
    }

    public function destroy(Request $request, Laboratoire $laboratoire)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('labo.result.write'))) {
            return response()->json(['message' => 'Forbidden: labo.result.write requis'], 403);
        }
        $laboratoire->delete();
        return response()->noContent();
    }
}
