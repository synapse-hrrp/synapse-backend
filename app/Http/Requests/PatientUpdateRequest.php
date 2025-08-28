<?php
// app/Http/Requests/PatientUpdateRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array {
        $patientId = $this->route('patient')?->id ?? $this->route('patient');
        return [
            'numero_dossier' => [
                'sometimes','string','max:32','regex:/^[A-Z]{3}-\d{4}-\d{6}$/',
                Rule::unique('patients','numero_dossier')->ignore($patientId)
            ],
            'nom' => ['sometimes','string','min:1','max:100'],
            'prenom' => ['sometimes','string','min:1','max:100'],

            'date_naissance' => ['sometimes','nullable','date','before:today'],
            'lieu_naissance' => ['sometimes','nullable','string','max:150'],
            'age_reporte' => ['sometimes','nullable','integer','min:0','max:140'],
            'sexe' => ['sometimes','nullable','in:M,F,X'],

            'nationalite' => ['sometimes','nullable','string','max:80'],
            'profession' => ['sometimes','nullable','string','max:120'],
            'adresse' => ['sometimes','nullable','string'],
            'quartier' => ['sometimes','nullable','string','max:120'],
            'telephone' => ['sometimes','nullable','string','min:6','max:30'],
            'statut_matrimonial' => ['sometimes','nullable','string','max:40'],

            'proche_nom' => ['sometimes','nullable','string','max:150'],
            'proche_tel' => ['sometimes','nullable','string','max:30'],

            'groupe_sanguin' => ['sometimes','nullable','in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'allergies' => ['sometimes','nullable','string'],

            'assurance_id' => ['sometimes','nullable','uuid'],
            'numero_assure' => ['sometimes','nullable','string','max:64'],

            'is_active' => ['sometimes','boolean'],
        ];
    }
}
