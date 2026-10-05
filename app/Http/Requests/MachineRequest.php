<?php

namespace App\Http\Requests;

use App\Models\Machine;
use App\Models\MachineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $machine = $this->route('machine');

        return $machine
            ? $this->user()->can('update', $machine)
            : $this->user()->can('create', Machine::class);
    }

    public function rules(): array
    {
        /** @var Machine|null $machine */
        $machine = $this->route('machine');
        $locked = $machine?->transactions()->exists();

        return [
            // SKU and type are fixed once stock has been issued to the machine (SPEC 4.3).
            'sku' => $locked
                ? ['required', Rule::in([$machine->sku])]
                : ['required', 'string', 'regex:'.Machine::SKU_PATTERN, Rule::unique('machines', 'sku')->ignore($machine)],
            'machine_type_id' => $locked
                ? ['required', Rule::in([$machine->machine_type_id])]
                : ['required', 'integer', Rule::exists('machine_types', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:150'],
            'revision' => ['required', 'string', 'max:10', 'regex:'.Machine::REVISION_PATTERN],
            // Relocation is allowed: history keeps the site of each transaction (SPEC 5.8).
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['sku', 'machine_type_id'])) {
                return;
            }

            $type = MachineType::query()->find($this->input('machine_type_id'));

            if ($type && substr($this->input('sku'), -1) !== $type->code) {
                $validator->errors()->add('sku', __('A :type machine’s SKU must end in :letter, e.g. :example.', [
                    'type' => $type->name, 'letter' => $type->code, 'example' => $type->nextSku(),
                ]));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'sku.regex' => __('Use three digits followed by the type letter, e.g. 007C.'),
            'sku.in' => __('The SKU cannot change because stock has been issued to this machine.'),
            'machine_type_id.in' => __('The type cannot change because stock has been issued to this machine.'),
            'revision.regex' => __('Use the form major.minor, e.g. 2.0.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = MachineType::query()->find($this->input('machine_type_id'));

        $this->merge([
            'sku' => strtoupper(trim((string) $this->input('sku'))),
            'revision' => trim((string) $this->input('revision')),
            // The proper name defaults to the type name (SPEC 4.3).
            'name' => trim((string) $this->input('name')) ?: $type?->name,
        ]);
    }
}
