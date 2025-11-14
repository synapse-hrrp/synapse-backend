<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanningStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; } // TODO policy si besoin

    public function rules(): array
    {
        return [
            'segments' => ['required','array','min:1'],
            'segments.*.weekday' => ['required','integer','between:1,7'],
            'segments.*.start_time' => ['required','date_format:H:i'],
            'segments.*.end_time'   => ['required','date_format:H:i','after:segments.*.start_time'],
            'segments.*.slot_duration' => ['nullable','integer','between:5,180'],
            'segments.*.capacity_per_slot' => ['nullable','integer','between:1,20'],
            'segments.*.is_active' => ['nullable','boolean'],
        ];
    }
}
