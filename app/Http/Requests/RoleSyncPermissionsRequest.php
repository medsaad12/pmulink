<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoleSyncPermissionsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'permission_ids' => 'permissions',
            'permission_ids.*' => 'permission',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permission_ids.array' => 'La liste des permissions est invalide.',
            'permission_ids.*.integer' => 'Une permission sélectionnée est invalide.',
            'permission_ids.*.exists' => 'Une permission sélectionnée n’existe pas.',
        ];
    }
}
