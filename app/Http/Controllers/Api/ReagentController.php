<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Reagent;
use Illuminate\Http\Request;

class ReagentController extends Controller
{
    public function index(Request $req) {
        $q = Reagent::query();
        if ($s = $req->get('search')) {
            $q->where(fn($w)=>$w->where('name','like',"%$s%")->orWhere('sku','like',"%$s%"));
        }
        return $q->orderBy('name')->paginate(20);
    }

    public function store(Request $req) {
        $data = $req->validate([
            'name'=>'required|string',
            'sku'=>'required|string|unique:reagents,sku',
            'uom'=>'required|string',
            'cas_number'=>'nullable|string',
            'hazard_class'=>'nullable|string',
            'storage_temp_min'=>'nullable|numeric',
            'storage_temp_max'=>'nullable|numeric',
            'storage_conditions'=>'nullable|string',
            'concentration'=>'nullable|string',
            'container_size'=>'nullable|numeric',
            'location_default'=>'nullable|string',
            'min_stock'=>'nullable|numeric',
            'reorder_point'=>'nullable|numeric',
            'supplier_id'=>'nullable|integer',
        ]);
        return Reagent::create($data);
    }

    public function show(Reagent $reagent) { return $reagent->load('lots'); }

    public function update(Request $req, Reagent $reagent) {
        $data = $req->validate([
            'name'=>'sometimes|string',
            'sku'=>"sometimes|string|unique:reagents,sku,{$reagent->id}",
            'uom'=>'sometimes|string',
            'cas_number'=>'nullable|string',
            'hazard_class'=>'nullable|string',
            'storage_temp_min'=>'nullable|numeric',
            'storage_temp_max'=>'nullable|numeric',
            'storage_conditions'=>'nullable|string',
            'concentration'=>'nullable|string',
            'container_size'=>'nullable|numeric',
            'location_default'=>'nullable|string',
            'min_stock'=>'nullable|numeric',
            'reorder_point'=>'nullable|numeric',
            'supplier_id'=>'nullable|integer',
        ]);
        $reagent->update($data);
        return $reagent;
    }

    public function destroy(Reagent $reagent) { $reagent->delete(); return response()->noContent(); }

    public function stock(Reagent $reagent) {
        return [
            'id'=>$reagent->id,'sku'=>$reagent->sku,'name'=>$reagent->name,
            'uom'=>$reagent->uom,'current_stock'=>$reagent->current_stock
        ];
    }

    public function stockSummary() {
        return Reagent::select('id','sku','name','uom','current_stock','reorder_point')
            ->orderBy('name')->get();
    }
}
