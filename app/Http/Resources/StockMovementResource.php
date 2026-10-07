<?php

namespace App\Http\Resources;

use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockTransaction */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'type' => $this->type->value,
            'item' => ['id' => $this->item_id, 'sku' => $this->item->sku],
            'site' => $this->site->code,
            'qty_delta' => $this->qty_delta,
            'unit_cost' => $this->unit_cost,
            'value' => $this->value,
            'qty_after' => $this->qty_after,
            'avg_cost_after' => $this->avg_cost_after,
            'machine' => $this->machine?->sku,
            'counter_site' => $this->counterSite?->code,
            'purchase_order' => $this->purchaseOrderLine?->purchaseOrder->number,
            'stock_count' => $this->stockCount?->reference,
            'transfer_group' => $this->transfer_group,
            'reason' => $this->reasonCode?->code,
            'note' => $this->note,
            'recorded_by' => $this->user->name,
        ];
    }
}
