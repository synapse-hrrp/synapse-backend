<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // adapte si tu as des policies
    }

    public function rules(): array
    {
        $serviceId = $this->service?->id; // si update (slug binding)

        return [
            'name'      => ['required','string','max:150'],
            'code'      => ['nullable','string','max:50'],
            'slug'      => [
                'nullable','alpha_dash','max:160',
                'unique:services,slug'.($serviceId ? ",$serviceId" : '')
            ],
            'is_active' => ['sometimes','boolean'],

            // nouvelles colonnes
            'webhook_url'     => ['nullable','url','max:255'],
            'webhook_method'  => ['nullable','in:POST,PUT,PATCH'],
            'webhook_token'   => ['nullable','string','max:255'],
            'webhook_secret'  => ['nullable','string','max:255'],
            'webhook_event'   => ['nullable','string','max:100'],
            'webhook_enabled' => ['sometimes','boolean'],
        ];
    }
}
