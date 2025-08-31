<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // On protège déjà par 'auth:sanctum' + 'role:admin', donc true ici
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required','string','max:255'],
            'email'       => ['required','email','max:255','unique:users,email'],
            'password'    => ['required','string','min:8'],
            'phone'       => ['nullable','string','max:30'],
            'is_active'   => ['sometimes','boolean'],

            // Rôle & permissions
            'role'        => ['required','string'],          // ex: admin, medecin, receptionniste
            'permissions' => ['sometimes','array'],          // ex: ["patients.create","patients.view"]
            'permissions.*'=> ['string'],

            // Service principal (FK) OU plusieurs services via pivot
            //'service_id'  => ['nullable','integer','exists:services,id'],
            //'services'    => ['sometimes','array'],
            //'services.*'  => ['integer','exists:services,id'],
            //'primary_service_id' => ['nullable','integer','exists:services,id'],
        ];
    }
}
