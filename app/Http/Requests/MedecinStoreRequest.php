<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedecinStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // adapte si tu utilises Policies/Abilities
        return $this->user()?->can('medecins.create') ?? true;
    }

    public function rules(): array
    {
        return [
            'personnel_id' => ['required','integer','exists:personnels,id','unique:medecins,personnel_id'],
            'numero_ordre' => ['required','string','max:255','unique:medecins,numero_ordre'],
            'specialite'   => ['required','string','max:255'],
            'grade'        => ['nullable','string','max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'personnel_id' => 'personnel',
            'numero_ordre' => 'numéro d’ordre',
        ];
    }

    public function messages(): array
    {
        return [
            'personnel_id.unique' => 'Ce personnel possède déjà un profil médecin.',
            'numero_ordre.unique' => 'Ce numéro d’ordre est déjà utilisé.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_ordre' => $this->numero_ordre ? trim($this->numero_ordre) : null,
            'specialite'   => $this->specialite   ? trim($this->specialite)   : null,
            'grade'        => $this->grade        ? trim($this->grade)        : null,
        ]);
    }
}
