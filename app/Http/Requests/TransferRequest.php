<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transfer in from another site, entered by the receiving site on arrival (SPEC 5.2; screen 10a).
 */
class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $from = Site::query()->find($this->input('from_site_id'));
        $to = Site::query()->find($this->input('to_site_id'));

        return $from && $to && $this->user()->can('transfer', [Stock::class, $from, $to]);
    }

    public function rules(): array
    {
        return [
            'to_site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'from_site_id' => ['required', 'integer', 'different:to_site_id', Rule::exists('sites', 'id')->where('is_active', true)],
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'qty' => ['required', 'regex:/^\d{1,11}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => __('Pick an item from the list.'),
            'from_site_id.different' => __('Choose the site the goods came from.'),
            'qty.regex' => __('Enter a positive quantity with at most 3 decimals.'),
            'qty.not_regex' => __('Enter a positive quantity with at most 3 decimals.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['qty' => trim((string) $this->input('qty'))]);
    }
}
