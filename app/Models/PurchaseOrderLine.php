<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * qty_received and is_closed change only through receiving and close-short actions.
 */
#[Fillable(['purchase_order_id', 'item_id', 'qty_ordered', 'unit_price'])]
class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:3',
            'qty_received' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'is_closed' => 'boolean',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    /**
     * Quantity still expected on open orders, per item and site (SPEC 5.5): shown next to a
     * shortage, never added to available stock.
     *
     * @param  list<int>  $itemIds
     * @return array<int, array<int, string>> item id => site id => quantity
     */
    public static function onOrder(array $itemIds, ?int $siteId = null): array
    {
        if ($itemIds === []) {
            return [];
        }

        $open = array_map(fn (PurchaseOrderStatus $s) => $s->value, array_filter(PurchaseOrderStatus::cases(), fn ($s) => $s->acceptsReceipts()));

        $rows = static::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->whereIn('purchase_order_lines.item_id', $itemIds)
            ->whereIn('purchase_orders.status', $open)
            ->where('purchase_order_lines.is_closed', false)
            ->when($siteId, fn ($q) => $q->where('purchase_orders.site_id', $siteId))
            ->groupBy('purchase_order_lines.item_id', 'purchase_orders.site_id')
            ->selectRaw('purchase_order_lines.item_id, purchase_orders.site_id, SUM(GREATEST(qty_ordered - qty_received, 0)) AS qty')
            ->havingRaw('qty > 0')
            ->toBase()
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->item_id][$row->site_id] = $row->qty;
        }

        return $result;
    }

    /**
     * Orders already under way per item and site, drafts included, so nobody orders the same
     * shortage twice. Lines closed short or received in full are left out.
     *
     * @param  list<int>  $itemIds
     * @return array<int, array<int, list<array{id: int, number: string, status: PurchaseOrderStatus, qty: string}>>> item id => site id => orders
     */
    public static function inProgress(array $itemIds, ?int $siteId = null): array
    {
        if ($itemIds === []) {
            return [];
        }

        $statuses = [PurchaseOrderStatus::Draft->value, ...array_map(fn (PurchaseOrderStatus $s) => $s->value,
            array_filter(PurchaseOrderStatus::cases(), fn ($s) => $s->acceptsReceipts()))];

        $rows = static::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->whereIn('purchase_order_lines.item_id', $itemIds)
            ->whereIn('purchase_orders.status', $statuses)
            ->where('purchase_order_lines.is_closed', false)
            ->whereColumn('purchase_order_lines.qty_received', '<', 'purchase_order_lines.qty_ordered')
            ->when($siteId, fn ($q) => $q->where('purchase_orders.site_id', $siteId))
            ->groupBy('purchase_order_lines.item_id', 'purchase_orders.site_id', 'purchase_orders.id', 'purchase_orders.number', 'purchase_orders.status')
            ->selectRaw('purchase_order_lines.item_id, purchase_orders.site_id, purchase_orders.id, purchase_orders.number,
                purchase_orders.status, SUM(qty_ordered - qty_received) AS qty')
            ->orderBy('purchase_orders.id')
            ->toBase()
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->item_id][$row->site_id][] = [
                'id' => (int) $row->id,
                'number' => $row->number,
                'status' => PurchaseOrderStatus::from($row->status),
                'qty' => Decimal::round((string) $row->qty, 3),
            ];
        }

        return $result;
    }

    public function value(): string
    {
        return Decimal::round(Decimal::mul($this->qty_ordered, $this->unit_price), 4);
    }

    public function outstanding(): string
    {
        $outstanding = bcsub($this->qty_ordered, $this->qty_received, 3);

        return bccomp($outstanding, '0', 3) > 0 ? $outstanding : '0.000';
    }

    public function isFulfilled(): bool
    {
        return $this->is_closed || bccomp($this->qty_received, $this->qty_ordered, 3) >= 0;
    }
}
