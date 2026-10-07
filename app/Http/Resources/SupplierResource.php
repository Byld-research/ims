<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'lead_time_days' => $this->lead_time_days,
            'is_active' => $this->is_active,
            'items' => $this->whenLoaded('supplierItems', fn () => $this->supplierItems->map(fn ($link) => [
                'item_id' => $link->item_id,
                'sku' => $link->item->sku,
                'name' => $link->item->name,
                'supplier_sku' => $link->supplier_sku,
                'last_price' => $link->last_price,
                'pack_size' => $link->pack_size,
            ])),
        ];
    }
}
