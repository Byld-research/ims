<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Item */
class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category' => ['id' => $this->category_id, 'full_name' => $this->category->fullName()],
            'uom' => $this->uom,
            'criticality' => $this->criticality?->value,
            'manufacturer' => $this->manufacturer,
            'mpn' => $this->mpn,
            'drawing_no' => $this->drawing_no,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            'stock' => StockResource::collection($this->whenLoaded('stocks')),
            'suppliers' => $this->whenLoaded('supplierItems', fn () => $this->supplierItems->map(fn ($link) => [
                'supplier_id' => $link->supplier_id,
                'supplier' => $link->supplier->name,
                'supplier_sku' => $link->supplier_sku,
                'last_price' => $link->last_price,
                'pack_size' => $link->pack_size,
            ])),
        ];
    }
}
