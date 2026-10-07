<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'supplier' => ['id' => $this->supplier_id, 'name' => $this->supplier->name],
            'site' => $this->site->code,
            'ordered_at' => $this->ordered_at?->toDateString(),
            'confirmed_at' => $this->confirmed_at?->toDateString(),
            'eta' => $this->eta?->toDateString(),
            'shipped_at' => $this->shipped_at?->toDateString(),
            'tracking_ref' => $this->tracking_ref,
            'closed_at' => $this->closed_at?->toDateString(),
            'total' => $this->lines->reduce(fn ($sum, $line) => bcadd($sum, $line->value(), 4), '0.0000'),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            'lines' => $this->lines->map(fn ($line) => [
                'item' => ['id' => $line->item_id, 'sku' => $line->item->sku, 'name' => $line->item->name, 'uom' => $line->item->uom],
                'qty_ordered' => $line->qty_ordered,
                'qty_received' => $line->qty_received,
                'qty_outstanding' => $line->is_closed ? '0.000' : $line->outstanding(),
                'unit_price' => $line->unit_price,
                'value' => $line->value(),
                'is_closed' => $line->is_closed,
            ]),
        ];
    }
}
