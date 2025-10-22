<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Controller;
use App\Models\Pharmacie\Dci;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DciController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q',''));
        $per = max(1, min((int)$request->query('per_page',20), 100));

        $query = Dci::query();
        if ($q !== '') {
            $like = '%'.preg_replace('/\s+/', '%', $q).'%';
            $query->where('name','like',$like);
        }
        return response()->json($query->orderBy('name')->paginate($per));
    }

    public function options()
    {
        return response()->json(Dci::orderBy('name')->get(['id','name']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required','string','max:190','unique:dcis,name'],
            'description' => ['nullable','string','max:500'],
        ]);
        return response()->json(Dci::create($data), 201);
    }

    public function show(Dci $dci) { return response()->json($dci); }

    public function update(Request $request, Dci $dci)
    {
        $data = $request->validate([
            'name'        => ['sometimes','string','max:190', Rule::unique('dcis','name')->ignore($dci->id)],
            'description' => ['sometimes','nullable','string','max:500'],
        ]);
        $dci->update($data);
        return response()->json($dci);
    }

    public function destroy(Dci $dci)
    {
        $dci->delete();
        return response()->json(['message'=>'DCI supprimée']);
    }
}
