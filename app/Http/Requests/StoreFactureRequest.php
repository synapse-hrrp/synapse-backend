<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactureRequest extends FormRequest
{
    public function authorize(): bool { return true; } // ou Policy

    public function rules(): array
    {
        return [
            'visite_id' => ['required','uuid','exists:visites,id'],
        ];
    }
}
