<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One physical machine installed at a site (SPEC 1a, 4.3). SKU: serial + type letter, e.g. 004C.
 */
#[Fillable(['sku', 'machine_type_id', 'name', 'revision', 'site_id', 'is_active'])]
class Machine extends Model
{
    use HasFactory;

    public const SKU_PATTERN = '/^(\d{3})([A-Z])$/';

    public const REVISION_PATTERN = '/^\d{1,3}\.\d{1,3}$/';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Keep the type's serial counter at or above every serial in use (SPEC 5.8).
        // A single atomic UPDATE, so concurrent registrations cannot lower it.
        static::saved(function (Machine $machine) {
            if ($serial = $machine->serial()) {
                DB::table('machine_types')
                    ->where('id', $machine->machine_type_id)
                    ->update(['last_serial' => DB::raw('GREATEST(last_serial, '.(int) $serial.')')]);
            }
        });
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function machineType(): BelongsTo
    {
        return $this->belongsTo(MachineType::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function displayName(): string
    {
        return $this->name.' '.$this->revision;
    }

    public function serial(): ?int
    {
        return preg_match(self::SKU_PATTERN, (string) $this->sku, $m) ? (int) $m[1] : null;
    }

    /**
     * The parts list lines that apply to this machine's revision (SPEC 5.8).
     *
     * @return Collection<int, MachineTypeItem>
     */
    public function partsList(): Collection
    {
        return MachineTypeItem::query()
            ->where('machine_type_id', $this->machine_type_id)
            ->appliesToRevision($this->revision)
            ->with('item')
            ->join('items', 'items.id', '=', 'machine_type_items.item_id')
            ->orderBy('items.sku')
            ->select('machine_type_items.*')
            ->get();
    }
}
