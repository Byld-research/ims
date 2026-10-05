<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derived state: qty and avg_cost are written only by StockService and must be
 * reconstructible from stock_transactions (SPEC 4.6). They are deliberately not fillable.
 */
#[Fillable(['item_id', 'site_id', 'min_level', 'bin', 'is_kanban', 'bin_qty'])]
class Stock extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'avg_cost' => 'decimal:4',
            'min_level' => 'decimal:3',
            'is_kanban' => 'boolean',
            'bin_qty' => 'decimal:3',
            'last_counted_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function value(): string
    {
        return Decimal::round(Decimal::mul($this->qty, $this->avg_cost), 4);
    }

    /**
     * The SQL form of needsReplenishment() (SPEC 5.5).
     */
    public function scopeNeedsReplenishment(Builder $query): void
    {
        $query->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('is_kanban', false)->where('min_level', '>', 0)->whereColumn('qty', '<', 'min_level'))
            ->orWhere(fn ($q) => $q->where('is_kanban', true)->whereColumn('qty', '<=', 'bin_qty')));
    }

    /**
     * Low stock rule from SPEC 5.5. Kanban items ignore min_level.
     */
    public function needsReplenishment(): bool
    {
        if ($this->is_kanban) {
            return $this->bin_qty !== null && bccomp($this->qty, $this->bin_qty, 3) <= 0;
        }

        return bccomp($this->min_level, '0', 3) > 0 && bccomp($this->qty, $this->min_level, 3) < 0;
    }
}
