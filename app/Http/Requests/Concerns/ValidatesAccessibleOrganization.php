<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesAccessibleOrganization
{
    /**
     * @return array<int, mixed>
     */
    protected function accessibleOrganizationIdsRules(): array
    {
        return [
            'organization_ids' => ['required', 'array', 'min:1'],
            'organization_ids.*' => [
                'integer',
                Rule::exists('organizations', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->user()?->isSup() && ! $this->user()?->belongsToOrganization((int) $value)) {
                        $fail('Vous n’avez pas accès à cette organisation.');
                    }
                },
            ],
        ];
    }

    /**
     * @return list<int>
     */
    protected function organizationIdsFromInput(): array
    {
        return array_values(array_map(
            static fn ($id) => (int) $id,
            $this->input('organization_ids', []),
        ));
    }
}
