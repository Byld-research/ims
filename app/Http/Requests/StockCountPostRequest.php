<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockCountPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('post', $this->route('stockCount'));
    }

    public function rules(): array
    {
        return [
            'costs' => ['nullable', 'array'],
            'costs.*' => ['nullable', 'decimal:0,4', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function costs(): array
    {
        return collect($this->validated('costs') ?? [])->filter(fn ($cost) => $cost !== null && $cost !== '')
            ->mapWithKeys(fn ($cost, $lineId) => [(int) $lineId => (string) $cost])->all();
    }
}
