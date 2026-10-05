<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'is_structural', 'default_bin'])]
class Category extends Model
{
    use HasFactory;

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

    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' / '.$this->name : $this->name;
    }
}
