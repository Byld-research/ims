<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['machine_type_id', 'item_id', 'reference', 'qty_per_machine', 'is_consumable', 'note'])]
class MachineTypeItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'qty_per_machine' => 'decimal:3',
            'is_consumable' => 'boolean',
        ];
    }

    public function machineType(): BelongsTo
    {
        return $this->belongsTo(MachineType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
