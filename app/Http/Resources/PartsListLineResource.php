<?php

namespace App\Http\Resources;

use App\Models\MachineTypeItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MachineTypeItem */
class PartsListLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item' => ['id' => $this->item_id, 'sku' => $this->item->sku, 'name' => $this->item->name, 'uom' => $this->item->uom],
            'revision' => $this->revision,
            'reference' => $this->reference,
            'qty_per_machine' => $this->qty_per_machine,
            'is_consumable' => $this->is_consumable,
            'note' => $this->note,
        ];
    }
}
