<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesFaitMarquantResponsableAction
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function responsableActionIdRules(): array
    {
        return [
            'responsable_action_id' => [
                'required',
                'integer',
                Rule::exists('organization_user', 'user_id')
                    ->where('organization_id', (int) app('currentOrganizationId')),
            ],
        ];
    }
}
