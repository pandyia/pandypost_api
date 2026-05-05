<?php

namespace App\Http\Requests;

use App\Enums\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // "scheduled" is excluded: it can only be set by the system, never manually.
        $allowedStages = array_map(
            fn(PipelineStage $s) => $s->value,
            PipelineStage::manualStages()
        );

        return [
            'stage' => ['required', Rule::in($allowedStages)],
        ];
    }

    public function messages(): array
    {
        return [
            'stage.required' => 'A etapa de destino é obrigatória.',
            'stage.in'       => 'Etapa inválida. Valores permitidos: idea, script, recorded, editing, ready.',
        ];
    }
}
