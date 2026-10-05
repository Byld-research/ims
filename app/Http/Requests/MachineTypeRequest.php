<?php

namespace App\Http\Requests;

use App\Models\MachineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MachineTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $machineType = $this->route('machineType');

        return $machineType
            ? $this->user()->can('update', $machineType)
            : $this->user()->can('create', MachineType::class);
    }

    public function rules(): array
    {
        /** @var MachineType|null $machineType */
        $machineType = $this->route('machineType');

        return [
            // The letter is part of every machine SKU, so it cannot change once machines exist.
            'code' => $machineType?->machines()->exists()
                ? ['required', Rule::in([$machineType->code])]
                : ['required', 'string', 'size:1', 'regex:/^[A-Z]$/', Rule::unique('machine_types', 'code')->ignore($machineType)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'last_serial' => ['required', 'integer', 'min:'.($machineType ? $this->highestRegisteredSerial($machineType) : 0), 'max:999'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => __('Use a single capital letter, e.g. C.'),
            'code.in' => __('The letter cannot change because machines of this type are registered.'),
            'last_serial.min' => __('The last serial cannot be lower than a serial already in the register (:min).'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'last_serial' => $this->input('last_serial', 0) ?: 0,
        ]);
    }

    private function highestRegisteredSerial(MachineType $machineType): int
    {
        return (int) $machineType->machines()->pluck('sku')->map(fn ($sku) => (int) substr($sku, 0, 3))->max();
    }
}
