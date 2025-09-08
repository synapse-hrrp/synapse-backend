<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentStoreRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Finance\Payment;

class PaymentController extends Controller
{
    // POST /api/v1/finance/payments
    public function store(PaymentStoreRequest $request)
    {
        $data = $request->validated();
        $data['recu_par'] = $request->user()->id ?? null;

        $payment = Payment::create($data);
        $payment->load(['recuPar:id,name,email']);

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }
}
