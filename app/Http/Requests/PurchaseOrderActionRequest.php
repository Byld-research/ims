<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Status actions on an order (SPEC 5.3). Each action asks only for what its step records.
 */
class PurchaseOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('purchaseOrder') ?? $this->route('purchaseOrderLine')?->purchaseOrder;

        return $order !== null && $this->user()->can('update', $order);
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'confirm' => ['eta' => ['required', 'date']],
            'ship' => ['tracking_ref' => ['required', 'string', 'max:200']],
            'cancel', 'closeShort' => ['reason' => ['required', 'string', 'max:500']],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'eta.required' => __('Enter the delivery date the supplier confirmed.'),
            'tracking_ref.required' => __('Enter the tracking number or link.'),
            'reason.required' => __('Say why, so the next person understands.'),
        ];
    }
}
