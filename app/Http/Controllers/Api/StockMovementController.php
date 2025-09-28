<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reagent;
use App\Models\ReagentLot;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $req, Reagent $reagent) {
        $q = $reagent->movements()->with(['lot','location'])->latest('moved_at');
        if ($t = $req->get('type')) $q->where('type',$t);
        if ($from = $req->get('from')) $q->whereDate('moved_at','>=',$from);
        if ($to = $req->get('to')) $q->whereDate('moved_at','<=',$to);
        return $q->paginate(100);
    }

    public function consumeFefo(Request $req, Reagent $reagent, StockService $svc) {
        $data = $req->validate([
            'quantity'  => 'required|numeric|min:0.000001',
            'reference' => 'nullable|string',
            'notes'     => 'nullable|string',
        ]);

        $movs = $svc->consumeFEFO($reagent, (float)$data['quantity'], [
            'reference' => $data['reference'] ?? null,
            'notes'     => $data['notes'] ?? null,
            'user_id'   => optional($req->user())->id,
        ]);

        return [
            'movements'     => $movs,
            'current_stock' => $reagent->fresh()->current_stock,
        ];
    }

    public function transfer(Request $req, Reagent $reagent, StockService $svc) {
        $data = $req->validate([
            'lot_id'         => 'required|integer|exists:reagent_lots,id',
            'to_location_id' => 'required|integer|exists:locations,id',
            'quantity'       => 'required|numeric|min:0.000001',
        ]);

        $lot = ReagentLot::where('reagent_id',$reagent->id)->findOrFail($data['lot_id']);

        $result = $svc->transferLot($lot, (int)$data['to_location_id'], (float)$data['quantity']);

        // On renvoie le mouvement + l’état des deux lots
        return response()->json([
            'movement' => $result['movement'],
            'from_lot' => $result['from_lot'],
            'to_lot'   => $result['to_lot'],
        ]);
    }

    public function report(Request $req) {
        $q = StockMovement::query()->with(['reagent:id,sku,name,uom','lot:id,lot_code,expiry_date','location']);
        if ($req->filled('type')) $q->where('type', $req->get('type'));
        if ($req->filled('from')) $q->whereDate('moved_at','>=',$req->get('from'));
        if ($req->filled('to')) $q->whereDate('moved_at','<=',$req->get('to'));
        if ($req->filled('sku')) $q->whereHas('reagent', fn($w)=>$w->where('sku',$req->get('sku')));
        return $q->orderBy('moved_at','desc')->paginate(200);
    }
}
