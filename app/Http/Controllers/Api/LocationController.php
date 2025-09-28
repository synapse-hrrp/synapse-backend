<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;


use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    // GET /inventory/locations
    public function index(Request $req)
    {
        $q = Location::query();

        // filtres simples (optionnels)
        if ($search = $req->get('q')) {
            $q->where(fn($w) =>
                $w->where('name', 'like', "%$search%")
                  ->orWhere('path', 'like', "%$search%")
            );
        }
        if ($req->filled('is_cold_chain')) {
            $q->where('is_cold_chain', (bool) $req->boolean('is_cold_chain'));
        }

        // si tu veux paginer : ?per_page=50
        if ($req->filled('per_page')) {
            return $q->orderBy('name')->paginate((int)$req->get('per_page', 50));
        }

        return $q->orderBy('name')->get();
    }

    // POST /inventory/locations
    public function store(Request $req)
    {
        $data = $req->validate([
            'name'            => 'required|string|max:255',
            'path'            => 'nullable|string|max:255',
            'is_cold_chain'   => 'boolean',
            'temp_range_min'  => 'nullable|numeric',
            'temp_range_max'  => 'nullable|numeric',
        ]);

        $loc = Location::create($data);
        return response()->json($loc, 201);
    }

    // GET /inventory/locations/{location}
    public function show(Location $location)
    {
        return $location;
    }

    // PATCH/PUT /inventory/locations/{location}
    public function update(Request $req, Location $location)
    {
        $data = $req->validate([
            'name'            => 'sometimes|string|max:255',
            'path'            => 'nullable|string|max:255',
            'is_cold_chain'   => 'boolean',
            'temp_range_min'  => 'nullable|numeric',
            'temp_range_max'  => 'nullable|numeric',
        ]);

        $location->update($data);
        return $location;
    }

    // DELETE /inventory/locations/{location}
    public function destroy(Location $location)
    {
        $location->delete();
        return response()->noContent();
    }
}
