<?php

namespace App\Http\Requests;

use App\Enums\ReasonCodeScope;
use App\Models\Site;
use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = Site::query()->find($this->input('site_id'));

        return $site !== null && $this->user()->can('adjust', [Stock::class, $site]);
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'direction' => ['required', Rule::in(['in', 'out'])],
            // Always positive: the direction carries the sign (SPEC 5.2.1).
            'qty' => ['required', 'regex:/^\d{1,11}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'reason_code_id' => ['required', 'integer', Rule::exists('reason_codes', 'id')
                ->where('applies_to', ReasonCodeScope::Adjustment->value)->where('is_active', true)],
            'unit_cost' => ['nullable', 'exclude_if:direction,out', 'decimal:0,4', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => __('Pick an item from the list.'),
            'qty.regex' => __('Enter a positive quantity with at most 3 decimals.'),
            'qty.not_regex' => __('Enter a positive quantity with at most 3 decimals.'),
        ];
    }

    public function site(): Site
    {
        return Site::query()->findOrFail($this->validated('site_id'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'qty' => trim((string) $this->input('qty')),
            'unit_cost' => trim((string) $this->input('unit_cost')) ?: null,
        ]);
    }
}
