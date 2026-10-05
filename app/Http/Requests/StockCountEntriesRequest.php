<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Counted quantities per line. Empty means not counted yet (SPEC 5.6.7); zero is a real count.
 */
class StockCountEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('stockCount'));
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array'],
            'lines.*.qty' => ['nullable', 'regex:/^\d{1,11}(\.\d{1,3})?$/'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['lines.*.qty.regex' => __('Counted quantities must be zero or positive, with at most 3 decimals.')];
    }

    /**
     * @return array<int, array{qty: ?string, note: ?string}>
     */
    public function entries(): array
    {
        return collect($this->validated('lines'))
            ->mapWithKeys(fn (array $entry, $lineId) => [(int) $lineId => [
                'qty' => isset($entry['qty']) && trim($entry['qty']) !== '' ? trim($entry['qty']) : null,
                'note' => isset($entry['note']) && trim($entry['note']) !== '' ? trim($entry['note']) : null,
            ]])
            ->all();
    }
}
