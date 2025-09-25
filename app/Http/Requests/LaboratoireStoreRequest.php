<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LaboratoireStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'    => ['required', 'uuid', 'exists:patients,id'],
            'visite_id'     => ['nullable', 'uuid', 'exists:visites,id'],
            'test_code'     => ['required', 'string', 'max:100'],
            'test_name'     => ['required', 'string', 'max:255'],
            'specimen'      => ['nullable', 'string', 'max:255'],
            'status'        => ['nullable', 'in:pending,in_progress,completed,cancelled'],
            'result_value'  => ['nullable', 'string', 'max:255'],
            'unit'          => ['nullable', 'string', 'max:50'],
            'ref_range'     => ['nullable', 'string', 'max:100'],
            'result_json'   => ['nullable', 'array'],
            'price'         => ['nullable', 'numeric', 'min:0'],
            'currency'      => ['nullable', 'string', 'size:3'],
            'invoice_id'    => ['nullable', 'uuid', 'exists:invoices,id'],
            'requested_by'  => ['required', 'uuid', 'exists:users,id'],
            'requested_at'  => ['nullable', 'date'],
            'validated_by'  => ['nullable', 'uuid', 'exists:users,id'],
            'validated_at'  => ['nullable', 'date'],
        ];
    }
}
