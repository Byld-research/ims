<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store: route {purchaseOrder}; update: route {purchaseOrderLine}. Quantities in the item's unit.
 */
class PurchaseOrderLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->order());
    }

    public function rules(): array
    {
        $rules = [
            'qty_ordered' => ['required', 'regex:/^\d{1,11}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'unit_price' => [$this->route('purchaseOrderLine') ? 'required' : 'nullable', 'decimal:0,4', 'min:0', 'max:9999999999'],
        ];

        if (! $this->route('purchaseOrderLine')) {
            $rules['item_id'] = ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'item_id.required' => __('Pick an item from the list.'),
            'qty_ordered.regex' => __('Enter a positive quantity with at most 3 decimals.'),
            'qty_ordered.not_regex' => __('Enter a positive quantity with at most 3 decimals.'),
        ];
    }

    public function order(): PurchaseOrder
    {
        return $this->route('purchaseOrder') ?? $this->route('purchaseOrderLine')->purchaseOrder;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'qty_ordered' => trim((string) $this->input('qty_ordered')),
            'unit_price' => trim((string) $this->input('unit_price')) ?: null,
        ]);
    }
}
