<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use App\Models\SupplierItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Links an item to a supplier. Store: route {supplier}; update: route {supplierItem}.
 */
class SupplierItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->supplier());
    }

    public function rules(): array
    {
        /** @var SupplierItem|null $supplierItem */
        $supplierItem = $this->route('supplierItem');

        $rules = [
            'supplier_sku' => ['nullable', 'string', 'max:80'],
            'last_price' => ['nullable', 'decimal:0,4', 'min:0', 'max:9999999999'],
            'pack_size' => ['required', 'decimal:0,3', 'gt:0', 'max:99999999999'],
        ];

        if (! $supplierItem) {
            $rules['item_id'] = ['required', 'integer',
                Rule::exists('items', 'id')->where('is_active', true),
                Rule::unique('supplier_items')->where('supplier_id', $this->supplier()->id)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'item_id.required' => __('Pick an item from the list.'),
            'item_id.unique' => __('This item is already linked to the supplier.'),
        ];
    }

    public function supplier(): Supplier
    {
        return $this->route('supplier') ?? $this->route('supplierItem')->supplier;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['pack_size' => $this->input('pack_size') ?: '1']);
    }
}
