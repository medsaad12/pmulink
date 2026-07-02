<?php

namespace App\Http\Requests;

use App\Models\FaitMarquant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaitMarquantStickySyncRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'projectName' => ['required', 'string', 'max:255'],
            'fait_status_id' => ['required', 'integer', 'exists:fait_statuses,id'],
            'faitsMarquants' => ['sometimes', 'array'],
            'faitsMarquants.*' => ['string', 'max:1000'],
            'commentaires' => ['sometimes', 'array'],
            'commentaires.*' => ['string', 'max:1000'],
            'workflow_action' => ['sometimes', Rule::in(['cloture', 'archive'])],
        ];
    }

    public function authorize(): bool
    {
        /** @var FaitMarquant $faitMarquant */
        $faitMarquant = $this->route('faitMarquant');

        $user = $this->user();

        return $user !== null && $faitMarquant->allowsCollaborationFrom($user);
    }
}
