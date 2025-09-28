<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Reagent;
use App\Models\ReagentLot;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function reorders() {
        return Reagent::select('id','sku','name','uom','current_stock','reorder_point')
            ->whereColumn('current_stock','<','reorder_point')
            ->orderByRaw('(reorder_point - current_stock) desc')
            ->get();
    }

    public function expiries(Request $req) {
        $days = (int)($req->get('days', 30));
        $limit = today()->addDays($days);
        return ReagentLot::with('reagent:id,sku,name,uom')
            ->where('status','ACTIVE')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [today(), $limit])
            ->orderBy('expiry_date','asc')
            ->get();
    }
}
