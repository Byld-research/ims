<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dates, tracking and notes. Dates are set automatically by the transitions and may be
 * corrected afterwards (SPEC 5.3.8).
 */
class PurchaseOrderDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('purchaseOrder'));
    }

    public function rules(): array
    {
        return [
            'ordered_at' => ['nullable', 'date'],
            'confirmed_at' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'shipped_at' => ['nullable', 'date'],
            'tracking_ref' => ['nullable', 'string', 'max:200'],
            'closed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
