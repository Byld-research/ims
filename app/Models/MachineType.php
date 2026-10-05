<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A family of machines identified by one letter, e.g. C · Truss Saw (SPEC 1a, 4.2).
 */
#[Fillable(['code', 'name', 'description', 'last_serial', 'is_active'])]
class MachineType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_serial' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function partsList(): HasMany
    {
        return $this->hasMany(MachineTypeItem::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'machine_type_items')
            ->withPivot(['revision', 'reference', 'qty_per_machine', 'is_consumable', 'note'])
            ->withTimestamps();
    }

    public function label(): string
    {
        return $this->code.' · '.$this->name;
    }

    /**
     * The SKU proposed for the next machine of this type: one past the higher of the
     * stored counter and the highest serial in the register (SPEC 5.8).
     */
    public function nextSku(): string
    {
        $highestRegistered = $this->machines()
            ->pluck('sku')
            ->map(fn (string $sku) => (int) substr($sku, 0, 3))
            ->max() ?? 0;

        return sprintf('%03d%s', max($this->last_serial, $highestRegistered) + 1, $this->code);
    }

    /**
     * Revisions in use by registered machines of this type, for pickers.
     *
     * @return list<string>
     */
    public function knownRevisions(): array
    {
        return $this->machines()->distinct()->orderBy('revision')->pluck('revision')
            ->merge($this->partsList()->whereNotNull('revision')->distinct()->pluck('revision'))
            ->unique()->sort(SORT_NATURAL)->values()->all();
    }
}
