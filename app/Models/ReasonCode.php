<?php

namespace App\Models;

use App\Enums\ReasonCodeScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['applies_to', 'code', 'label', 'is_active'])]
class ReasonCode extends Model
{
    use HasFactory;

    public const COUNT = 'COUNT';

    public const OPENING = 'OPENING';

    protected function casts(): array
    {
        return [
            'applies_to' => ReasonCodeScope::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeFor(Builder $query, ReasonCodeScope $scope): void
    {
        $query->where('applies_to', $scope);
    }

    public static function adjustment(string $code): self
    {
        return static::query()->for(ReasonCodeScope::Adjustment)->where('code', $code)->firstOrFail();
    }
}
