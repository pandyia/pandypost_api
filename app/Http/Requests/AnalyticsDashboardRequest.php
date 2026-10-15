<?php

namespace App\Http\Requests;

use App\Services\Analytics\AnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Os períodos aceitos dependem da plataforma da conta (capabilities do provider).
 */
class AnalyticsDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_range' => ['sometimes', 'string', Rule::in($this->capabilities()['date_ranges'])],
            'start_date' => ['required_if:date_range,custom', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['required_if:date_range,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $maxDays = $this->capabilities()['max_range_days'];

                if ($validator->errors()->isNotEmpty() || $this->input('date_range') !== 'custom' || $maxDays === null) {
                    return;
                }

                $days = CarbonImmutable::parse($this->input('start_date'))->diffInDays(CarbonImmutable::parse($this->input('end_date'))) + 1;

                if ($days > $maxDays) {
                    $validator->errors()->add('end_date', "Para esta plataforma o período pode ter no máximo {$maxDays} dias.");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'date_range.in' => 'Período não disponível para esta plataforma.',
            'start_date.required_if' => 'Informe a data inicial do período.',
            'end_date.required_if' => 'Informe a data final do período.',
            'end_date.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'end_date.before_or_equal' => 'A data final não pode ser no futuro.',
        ];
    }

    private function capabilities(): array
    {
        return app(AnalyticsService::class)->capabilities($this->route('socialAccount'));
    }
}
