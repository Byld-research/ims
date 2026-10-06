<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Quick order from the dashboard (SPEC 5.3a): lines keyed by stock row id. Only ticked lines
 * are validated; the controller checks each line's site against the user.
 */
class QuickOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Site::query()->active()->get()->contains(fn (Site $site) => $this->user()->can('create', [PurchaseOrder::class, $site]));
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array'],
            'lines.*.include' => ['nullable', 'boolean'],
            'lines.*.supplier_id' => ['exclude_unless:lines.*.include,1', 'required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'lines.*.qty' => ['exclude_unless:lines.*.include,1', 'required', 'regex:/^\d{1,11}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'lines.*.unit_price' => ['exclude_unless:lines.*.include,1', 'nullable', 'decimal:0,4', 'min:0', 'max:9999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.supplier_id.required' => __('Choose a supplier.'),
            'lines.*.qty.required' => __('Enter a positive quantity with at most 3 decimals.'),
            'lines.*.qty.regex' => __('Enter a positive quantity with at most 3 decimals.'),
            'lines.*.qty.not_regex' => __('Enter a positive quantity with at most 3 decimals.'),
        ];
    }

    /**
     * Ticked lines only, keyed by stock id.
     *
     * @return array<int, array{supplier_id: int, qty: string, unit_price: ?string}>
     */
    public function ticked(): array
    {
        return collect($this->validated('lines'))
            ->filter(fn ($line) => (bool) ($line['include'] ?? false))
            ->map(fn ($line) => [
                'supplier_id' => (int) $line['supplier_id'],
                'qty' => (string) $line['qty'],
                'unit_price' => $line['unit_price'] ?? null,
            ])
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $lines = collect((array) $this->input('lines', []))->map(fn ($line) => is_array($line) ? [
            ...$line,
            'qty' => trim((string) ($line['qty'] ?? '')),
            'unit_price' => trim((string) ($line['unit_price'] ?? '')) ?: null,
        ] : $line);

        $this->merge(['lines' => $lines->all()]);
    }
}
