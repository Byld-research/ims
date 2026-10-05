<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Models\StockCount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = Site::query()->find($this->input('site_id'));

        return $site !== null && $this->user()->can('create', [StockCount::class, $site]);
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'scope_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
