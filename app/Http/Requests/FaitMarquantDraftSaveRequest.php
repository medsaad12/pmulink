<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesFaitMarquantResponsableAction;
use App\Http\Requests\Concerns\ValidatesProchainesEtapes;
use App\Models\FaitMarquant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaitMarquantDraftSaveRequest extends FormRequest
{
    use ValidatesFaitMarquantResponsableAction;
    use ValidatesProchainesEtapes;

    protected function prepareForValidation(): void
    {
        $this->prepareProchainesEtapesForValidation();
    }

    public function authorize(): bool
    {
        $user = $this->user();
        /** @var FaitMarquant|null $fait */
        $fait = $this->route('faitMarquant');

        if ($user === null || $user->isGlobalUser()) {
            return false;
        }

        return $fait instanceof FaitMarquant && $fait->allowsCollaborationFrom($user);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'fait_status_id' => ['required', 'integer', 'exists:fait_statuses,id'],
            'status_id' => ['required', 'integer', 'exists:statuses,id'],
            'department_id' => ['required', 'integer', Rule::in($this->allowedDepartmentIds())],
            ...$this->responsableActionIdRules(),
            ...$this->prochainesEtapesRules(),
            'commentaires' => ['sometimes', 'array'],
            'commentaires.*' => ['string', 'max:1000'],
        ];
    }

    /**
     * Départements que l'utilisateur peut affecter au fait : les siens, plus le
     * département courant du fait (pour un responsable d'action hors département,
     * qui doit pouvoir le conserver tel quel).
     *
     * @return list<int>
     */
    private function allowedDepartmentIds(): array
    {
        $ids = $this->user()?->departmentIds() ?? [];

        /** @var FaitMarquant|null $fait */
        $fait = $this->route('faitMarquant');
        if ($fait instanceof FaitMarquant && $fait->department_id !== null) {
            $ids[] = (int) $fait->department_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'faits marquant',
            'fait_status_id' => 'statut du fait',
            'status_id' => 'statut workflow',
            'department_id' => 'département',
            'responsable_action_id' => 'responsable action',
            ...$this->prochainesEtapesAttributes(),
            'commentaires' => 'commentaires',
            'commentaires.*' => 'commentaire',
        ];
    }
}
