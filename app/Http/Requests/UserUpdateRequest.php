<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Http\Requests\Concerns\ValidatesAccessibleOrganization;
use App\Models\Department;
use App\Models\Role;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    use ProfileValidationRules;
    use ValidatesAccessibleOrganization;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            ...$this->profileRules($user->id),
            ...$this->accessibleOrganizationIdsRules(),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'role_id' => $this->roleRules(),
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => $this->departmentRules(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function roleRules(): array
    {
        return [
            'required',
            'integer',
            Rule::exists('roles', 'id'),
            function (string $attribute, mixed $value, \Closure $fail): void {
                $role = Role::query()
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->find((int) $value);

                if ($role === null) {
                    return;
                }

                $organizationIds = $this->organizationIdsFromInput();

                if (! in_array((int) $role->organization_id, $organizationIds, true)) {
                    $fail('Le rôle sélectionné ne correspond pas aux organisations choisies.');

                    return;
                }

                foreach ($organizationIds as $organizationId) {
                    $exists = Role::query()
                        ->withoutGlobalScope(OrganizationScope::class)
                        ->where('organization_id', $organizationId)
                        ->where('name', $role->name)
                        ->exists();

                    if (! $exists) {
                        $fail('Le rôle sélectionné n’existe pas dans toutes les organisations choisies.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function departmentRules(): array
    {
        return [
            'integer',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $department = Department::query()
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->find((int) $value);

                if ($department === null) {
                    return;
                }

                if (! in_array((int) $department->organization_id, $this->organizationIdsFromInput(), true)) {
                    $fail('Le département sélectionné ne correspond pas aux organisations choisies.');
                }
            },
        ];
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
            'organization_ids' => 'organisations',
            'organization_ids.*' => 'organisation',
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
            'organization_ids.required' => 'Veuillez sélectionner au moins une organisation.',
            'organization_ids.min' => 'Veuillez sélectionner au moins une organisation.',
            'role_id.required' => 'Veuillez sélectionner un rôle pour cet utilisateur.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
