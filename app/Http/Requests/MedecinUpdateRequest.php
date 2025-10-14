<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedecinUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('medecins.update') ?? true;
    }

    public function rules(): array
    {
        // Le paramètre de route s’appelle {medecin} -> $this->route('medecin')
        $medecinId = $this->route('medecin')?->id ?? $this->route('medecin');

        return [
            'personnel_id' => [
                'sometimes','integer','exists:personnels,id',
                Rule::unique('medecins','personnel_id')->ignore($medecinId),
            ],
            'numero_ordre' => [
                'sometimes','string','max:255',
                Rule::unique('medecins','numero_ordre')->ignore($medecinId),
            ],
            'specialite' => ['sometimes','string','max:255'],
            'grade'      => ['nullable','string','max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'personnel_id' => 'personnel',
            'numero_ordre' => 'numéro d’ordre',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_ordre' => $this->filled('numero_ordre') ? trim($this->numero_ordre) : $this->numero_ordre,
            'specialite'   => $this->filled('specialite')   ? trim($this->specialite)   : $this->specialite,
            'grade'        => $this->filled('grade')        ? trim($this->grade)        : $this->grade,
        ]);
    }
}
