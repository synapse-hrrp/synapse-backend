<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Personnel::with(['user:id,name,email', 'service:id,name'])
                ->latest('id')
                ->paginate($request->integer('per_page', 10))
        );
    }

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
        ]);

        $p = Personnel::create($data);

        return response()->json([
            'message' => 'Personnel créé',
            'data'    => $p->load(['user:id,name,email','service:id,name']),
        ], 201);
    }

    public function show(Personnel $personnel)
    {
        return response()->json(
            $personnel->load(['user:id,name,email','service:id,name'])
        );
    }

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
        ]);

        $personnel->update($data);

        return response()->json([
            'message' => 'Personnel modifié',
            'data'    => $personnel->load(['user:id,name,email','service:id,name']),
        ]);
    }

    public function destroy(Personnel $personnel)
    {
        $personnel->delete();

        return response()->json(['message' => 'Personnel supprimé']);
    }
}
