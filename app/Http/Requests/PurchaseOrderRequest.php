<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new draft order: supplier and destination site (SPEC 4.10, 3.8).
 */
class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = Site::query()->find($this->input('site_id'));

        return $site !== null && $this->user()->can('create', [PurchaseOrder::class, $site]);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
