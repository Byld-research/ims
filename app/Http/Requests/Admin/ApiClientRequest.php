<?php

namespace App\Http\Requests\Admin;

use App\Models\ApiClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('apiClient');

        return $client ? $this->user()->can('update', $client) : $this->user()->can('create', ApiClient::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('api_clients', 'name')->ignore($this->route('apiClient'))],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['site_id' => __('site')];
    }
}
