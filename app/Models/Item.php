<?php

namespace App\Models;

use App\Enums\Criticality;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sku', 'name', 'description', 'category_id', 'uom', 'manufacturer', 'mpn', 'drawing_no', 'criticality', 'is_active'])]
class Item extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'criticality' => Criticality::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function stockCountLines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function supplierItems(): HasMany
    {
        return $this->hasMany(SupplierItem::class);
    }

    public function machineTypes(): BelongsToMany
    {
        return $this->belongsToMany(MachineType::class, 'machine_type_items')
            ->withPivot(['revision', 'reference', 'qty_per_machine', 'is_consumable', 'note'])
            ->withTimestamps();
    }

    /**
     * The SKU is immutable once the item has any stock transaction (SPEC 4.5).
     */
    public function isSkuLocked(): bool
    {
        return $this->exists && $this->transactions()->exists();
    }
}
