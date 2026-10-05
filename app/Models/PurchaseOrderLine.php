<?php

namespace App\Models;

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
