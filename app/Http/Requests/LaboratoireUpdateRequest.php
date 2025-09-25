<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LaboratoireUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'    => ['sometimes', 'uuid', 'exists:patients,id'],
            'visite_id'     => ['sometimes', 'uuid', 'exists:visites,id'],
            'test_code'     => ['sometimes', 'string', 'max:100'],
            'test_name'     => ['sometimes', 'string', 'max:255'],
            'specimen'      => ['sometimes', 'string', 'max:255'],
            'status'        => ['sometimes', 'in:pending,in_progress,completed,cancelled'],
            'result_value'  => ['sometimes', 'string', 'max:255'],
            'unit'          => ['sometimes', 'string', 'max:50'],
            'ref_range'     => ['sometimes', 'string', 'max:100'],
            'result_json'   => ['sometimes', 'array'],
            'price'         => ['sometimes', 'numeric', 'min:0'],
            'currency'      => ['sometimes', 'string', 'size:3'],
            'invoice_id'    => ['sometimes', 'uuid', 'exists:invoices,id'],
            'requested_by'  => ['sometimes', 'uuid', 'exists:users,id'],
            'requested_at'  => ['sometimes', 'date'],
            'validated_by'  => ['sometimes', 'uuid', 'exists:users,id'],
            'validated_at'  => ['sometimes', 'date'],
        ];
    }
}
