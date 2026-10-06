<?php

namespace App\Enums;

/**
 * How badly a missing item hurts (SPEC 4.5). High: its failure stops production and it is hard
 * to get. Drives dashboard order and the suggested count frequency.
 */
enum Criticality: string
{
    case High = 'HIGH';
    case Normal = 'NORMAL';
    case Low = 'LOW';

    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Normal => 'Normal',
            self::Low => 'Low',
        };
    }

    /**
     * Sort position, most critical first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::High => 1,
            self::Normal => 2,
            self::Low => 3,
        };
    }

    /**
     * Suggested days between cycle counts (SPEC 5.6).
     */
    public function countIntervalDays(): int
    {
        return match ($this) {
            self::High => 30,
            self::Normal, self::Low => 90,
        };
    }

    /**
     * SQL ordering for a criticality column: High, Normal, Low, then unset. Alphabetical order
     * would put Low before Normal.
     */
    public static function orderSql(string $column): string
    {
        return "{$column} IS NULL, FIELD({$column}, 'HIGH', 'NORMAL', 'LOW')";
    }

    /**
     * @return array<string, string> value => label, for selects
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
