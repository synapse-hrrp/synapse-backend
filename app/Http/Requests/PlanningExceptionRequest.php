<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanningExceptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date' => ['required','date'],
            'is_working' => ['required','boolean'],
            'start_time' => ['nullable','date_format:H:i'],
            'end_time'   => ['nullable','date_format:H:i','after:start_time'],
            'slot_duration' => ['nullable','integer','between:5,180'],
            'capacity_per_slot' => ['nullable','integer','between:1,20'],
            'reason' => ['nullable','string','max:500'],
        ];
    }
}
