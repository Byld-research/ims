<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A parts list line. Applies to every revision of the type when revision is null (SPEC 5.8).
 */
#[Fillable(['machine_type_id', 'item_id', 'revision', 'reference', 'qty_per_machine', 'is_consumable', 'note'])]
class MachineTypeItem extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'qty_per_machine' => 'decimal:3',
            'is_consumable' => 'boolean',
        ];
    }

    public function scopeAppliesToRevision(Builder $query, string $revision): void
    {
        $query->where(fn ($q) => $q->whereNull('machine_type_items.revision')->orWhere('machine_type_items.revision', $revision));
    }

    public function machineType(): BelongsTo
    {
        return $this->belongsTo(MachineType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Why a line for this item and revision cannot coexist with the existing list, or null if it can.
     * An item is listed either once for all revisions or once per specific revision, never both.
     */
    public static function conflict(int $machineTypeId, int $itemId, ?string $revision, ?int $ignoreId = null): ?string
    {
        $existing = static::query()
            ->where('machine_type_id', $machineTypeId)
            ->where('item_id', $itemId)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->pluck('revision');

        if ($existing->isEmpty()) {
            return null;
        }

        if ($revision === null) {
            return $existing->contains(null)
                ? __('This item is already on the parts list for all revisions.')
                : __('This item is already listed for specific revisions (:revisions). Remove those lines first, or add another specific revision.', ['revisions' => $existing->join(', ')]);
        }

        if ($existing->contains(null)) {
            return __('This item is already listed for all revisions. Limit that line to a revision first.');
        }

        return $existing->contains($revision)
            ? __('This item is already listed for revision :revision.', ['revision' => $revision])
            : null;
    }
}
