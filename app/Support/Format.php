<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Display formatting. Storage keeps full precision; rounding happens only here (SPEC 5.1).
 */
final class Format
{
    /**
     * Quantity to at most 3 places, trailing zeros dropped: 10, 2.5, 0.125.
     */
    public static function qty(string|int|null $value): string
    {
        if ($value === null) {
            return '';
        }

        $rounded = Decimal::round($value, 3);
        [$whole, $fraction] = explode('.', $rounded) + [1 => ''];
        $fraction = rtrim($fraction, '0');

        return self::groupThousands($whole).($fraction === '' ? '' : '.'.$fraction);
    }

    /**
     * USD amount to 2 places with thousands separators: 1,234.57.
     */
    public static function money(string|int|null $value): string
    {
        if ($value === null) {
            return '';
        }

        [$whole, $fraction] = explode('.', Decimal::round($value, 2));

        return self::groupThousands($whole).'.'.$fraction;
    }

    /**
     * Timestamp stored in UTC, shown in the selected site's time zone (SPEC 2).
     */
    public static function datetime(?DateTimeInterface $value): string
    {
        if ($value === null) {
            return '';
        }

        return Carbon::instance($value)
            ->setTimezone(app(CurrentSite::class)->timezone())
            ->format('Y-m-d H:i');
    }

    public static function date(?DateTimeInterface $value): string
    {
        return $value?->format('Y-m-d') ?? '';
    }

    private static function groupThousands(string $whole): string
    {
        $negative = str_starts_with($whole, '-');
        $digits = ltrim($whole, '-');
        $grouped = strrev(implode(',', str_split(strrev($digits), 3)));

        return ($negative ? '-' : '').$grouped;
    }
}
