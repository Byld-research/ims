<?php

namespace App\Http\Requests;

use App\Models\MachineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A parts list line. Store: route {machineType}; update: route {machineTypeItem}.
 */
class MachineTypeItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->machineType());
    }

    public function rules(): array
    {
        $rules = [
            'reference' => ['nullable', 'string', 'max:80'],
            'qty_per_machine' => ['nullable', 'decimal:0,3', 'gt:0', 'max:99999999999'],
            'is_consumable' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ];

        if (! $this->route('machineTypeItem')) {
            $rules['item_id'] = ['required', 'integer',
                Rule::exists('items', 'id')->where('is_active', true),
                Rule::unique('machine_type_items')->where('machine_type_id', $this->machineType()->id)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'item_id.required' => __('Pick an item from the list.'),
            'item_id.unique' => __('This item is already on the parts list.'),
        ];
    }

    public function machineType(): MachineType
    {
        return $this->route('machineType') ?? $this->route('machineTypeItem')->machineType;
    }
}
