<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserStoreRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('organization_id', $this->currentOrganizationId())],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')->where('organization_id', $this->currentOrganizationId())],
        ];
    }

    private function currentOrganizationId(): int
    {
        return (int) app('currentOrganizationId');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
            'password_confirmation' => 'confirmation du mot de passe',
            'role_id' => 'rôle',
            'department_ids' => 'départements',
            'department_ids.*' => 'département',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_id.required' => 'Veuillez sélectionner un rôle pour cet utilisateur.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
