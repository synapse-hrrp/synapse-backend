<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RendezVousStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'medecin_id'   => ['required','exists:medecins,id'],
            'patient_id'   => ['required','exists:patients,id'],
            'service_slug' => ['required','exists:services,slug'],
            'tarif_id'     => ['nullable','integer','exists:tarifs,id'],
            'date'         => ['required','date'],
            'start_time'   => ['required','date_format:H:i'],
        ];
    }
}
