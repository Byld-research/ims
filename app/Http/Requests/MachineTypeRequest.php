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
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_\-]+$/',
                Rule::unique('machine_types', 'code')->ignore($this->route('machineType'))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => __('Use capital letters, digits, underscores and hyphens only, e.g. TRUSS_SAW.')];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }
}
