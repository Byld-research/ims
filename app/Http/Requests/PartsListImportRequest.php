<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartsListImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('machineType'));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
