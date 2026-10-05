<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'item_id', 'supplier_sku', 'last_price', 'pack_size'])]
class SupplierItem extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'last_price' => 'decimal:4',
            'pack_size' => 'decimal:3',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
