<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesProchainesEtapes
{
    protected function prepareProchainesEtapesForValidation(): void
    {
        if (! $this->has('prochaines_etapes')) {
            return;
        }

        $items = $this->input('prochaines_etapes', []);

        if (! is_array($items)) {
            return;
        }

        $normalized = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $normalized[] = ['body' => $item];

                continue;
            }

            if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        $this->merge(['prochaines_etapes' => $normalized]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function prochainesEtapesRules(): array
    {
        return [
            'prochaines_etapes' => ['sometimes', 'array'],
            'prochaines_etapes.*.body' => ['required', 'string', 'max:1000'],
            'prochaines_etapes.*.responsable_action_id' => [
                'sometimes',
                'integer',
                Rule::exists('organization_user', 'user_id')
                    ->where('organization_id', (int) app('currentOrganizationId')),
            ],
            'prochaines_etapes.*.deadline' => ['sometimes', 'nullable', 'date'],
            'prochaines_etapes.*.etape_status_id' => [
                'sometimes',
                'integer',
                'exists:etape_statuses,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function prochainesEtapesAttributes(): array
    {
        return [
            'prochaines_etapes' => 'prochaines étapes',
            'prochaines_etapes.*.body' => 'prochaine étape',
            'prochaines_etapes.*.responsable_action_id' => 'responsable action',
            'prochaines_etapes.*.deadline' => 'deadline',
            'prochaines_etapes.*.etape_status_id' => 'statut de l\'étape',
        ];
    }
}
