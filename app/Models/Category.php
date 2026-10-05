<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'is_structural', 'default_bin'])]
class Category extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['is_structural' => 'boolean'];
    }

    /**
     * Categories that items may be assigned to.
     */
    public function scopeAssignable(Builder $query): void
    {
        $query->where('is_structural', false);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Options for an item's category select, grouped under structural parents.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function assignableOptions(): array
    {
        $options = [];

        foreach (static::query()->whereNull('parent_id')->with('children')->orderBy('name')->get() as $top) {
            $children = $top->children->where('is_structural', false)->sortBy('name')->pluck('name', 'id')->all();

            if ($children) {
                $options[$top->name] = $children;
            } elseif (! $top->is_structural) {
                $options[$top->id] = $top->name;
            }
        }

        return $options;
    }

    /**
     * The category itself plus its children, for filtering by a parent.
     *
     * @return list<int>
     */
    public function selfAndChildIds(): array
    {
        return [$this->id, ...$this->children()->pluck('id')->all()];
    }

    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' / '.$this->name : $this->name;
    }
}
