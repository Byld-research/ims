<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Quantities received now, per line. Empty or zero means "nothing for this line" (SPEC 5.3).
 */
class ReceiveGoodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('purchaseOrder'));
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array'],
            'lines.*' => ['nullable', 'regex:/^\d{1,11}(\.\d{1,3})?$/'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->any() && $this->quantities() === []) {
                $validator->errors()->add('lines', __('Enter the quantity received for at least one line.'));
            }
        }];
    }

    public function messages(): array
    {
        return ['lines.*.regex' => __('Quantities must be positive numbers with at most 3 decimals.')];
    }

    /**
     * @return array<int, string> line id => quantity, zero and empty lines left out
     */
    public function quantities(): array
    {
        return collect((array) $this->input('lines'))
            ->map(fn ($qty) => trim((string) $qty))
            ->filter(fn ($qty) => $qty !== '' && preg_match('/^\d{1,11}(\.\d{1,3})?$/', $qty) && bccomp($qty, '0', 3) > 0)
            ->mapWithKeys(fn ($qty, $lineId) => [(int) $lineId => $qty])
            ->all();
    }
}
