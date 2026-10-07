<?php

namespace App\Http\Resources;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stock of one item at one site. Status follows the dashboard: out, below_min, refill
 * (a two-bin item down to one bin), ok, or no_minimum when nothing is watched (SPEC 5.5).
 *
 * @mixin Stock
 */
class StockResource extends JsonResource
{
    /** @var array<int, array<int, string>> item id => site id => quantity on open orders */
    public static array $onOrder = [];

    public function toArray(Request $request): array
    {
        return [
            'item' => $this->whenLoaded('item', fn () => ['id' => $this->item_id, 'sku' => $this->item->sku, 'name' => $this->item->name, 'uom' => $this->item->uom]),
            'site' => $this->site->code,
            'qty' => $this->qty,
            'min_level' => $this->min_level,
            'location' => $this->bin,
            'is_two_bin' => $this->is_kanban,
            'qty_per_bin' => $this->bin_qty,
            'avg_cost' => $this->avg_cost,
            'value' => $this->value(),
            'status' => $this->status(),
            'on_order' => self::$onOrder[$this->item_id][$this->site_id] ?? '0.000',
            'last_counted_at' => $this->last_counted_at?->toIso8601ZuluString(),
        ];
    }

    private function status(): string
    {
        if ($this->needsReplenishment()) {
            return match (true) {
                bccomp($this->qty, '0', 3) === 0 => 'out',
                $this->is_kanban => 'refill',
                default => 'below_min',
            };
        }

        return $this->is_kanban || bccomp($this->min_level, '0', 3) > 0 ? 'ok' : 'no_minimum';
    }
}
