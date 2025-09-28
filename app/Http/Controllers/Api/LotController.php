<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Reagent;
use App\Models\ReagentLot;
use App\Services\StockService;
use Illuminate\Http\Request;

class LotController extends Controller
{
    public function index(Request $req, Reagent $reagent) {
        $q = $reagent->lots()->with('location')->latest('received_at');
        if ($req->filled('status')) $q->where('status', $req->get('status'));
        return $q->paginate(50);
    }

    public function store(Request $req, Reagent $reagent, StockService $svc) {
        $data = $req->validate([
            'lot_code'=>'required|string',
            'received_at'=>'nullable|date',
            'expiry_date'=>'nullable|date',
            'quantity'=>'required|numeric|min:0.000001',
            'location_id'=>'nullable|integer|exists:locations,id',
            'unit_cost'=>'nullable|numeric',
            'coa_url'=>'nullable|string',
            'reference'=>'nullable|string',
        ]);

        $lot = $svc->receiveLot($reagent, [
            'lot_code'=>$data['lot_code'],
            'received_at'=>$data['received_at'] ?? now(),
            'expiry_date'=>$data['expiry_date'] ?? null,
            'location_id'=>$data['location_id'] ?? null,
            'status'=>'ACTIVE',
            'coa_url'=>$data['coa_url'] ?? null,
            'reference'=>$data['reference'] ?? null,
        ], (float)$data['quantity'], $data['unit_cost'] ?? null);

        return response()->json($lot->load('location'), 201);
    }

    public function quarantine(ReagentLot $lot) {
        $lot->update(['status'=>'QUARANTINE']);
        return $lot;
    }

    public function dispose(ReagentLot $lot, StockService $svc) {
        $mov = $svc->disposeLot($lot, 'Péremption / élimination');
        return ['movement'=>$mov, 'lot'=>$lot->fresh()];
    }
}
