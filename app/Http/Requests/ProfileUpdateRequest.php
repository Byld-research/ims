<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'notify_low_stock' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['notify_low_stock' => $this->boolean('notify_low_stock')]);
    }
}
