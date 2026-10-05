<?php

namespace App\Http\Requests;

use App\Models\Machine;
use App\Models\MachineType;
use App\Models\MachineTypeItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'revision' => ['nullable', 'string', 'max:10', 'regex:'.Machine::REVISION_PATTERN],
            'reference' => ['nullable', 'string', 'max:80'],
            'qty_per_machine' => ['nullable', 'decimal:0,3', 'gt:0', 'max:99999999999'],
            'is_consumable' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ];

        if (! $this->route('machineTypeItem')) {
            $rules['item_id'] = ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->any()) {
                return;
            }

            /** @var MachineTypeItem|null $line */
            $line = $this->route('machineTypeItem');

            $conflict = MachineTypeItem::conflict(
                $this->machineType()->id,
                $line?->item_id ?? (int) $this->input('item_id'),
                $this->input('revision'),
                $line?->id,
            );

            if ($conflict) {
                $validator->errors()->add($line ? 'revision' : 'item_id', $conflict);
            }
        }];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => __('Pick an item from the list.'),
            'revision.regex' => __('Use the form major.minor, e.g. 2.0, or leave empty for all revisions.'),
        ];
    }

    public function machineType(): MachineType
    {
        return $this->route('machineType') ?? $this->route('machineTypeItem')->machineType;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['revision' => trim((string) $this->input('revision')) ?: null]);
    }
}
