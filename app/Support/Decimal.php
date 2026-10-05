<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic on numeric strings (SPEC 2: never float).
 *
 * bcmath truncates to the requested scale; every stored money or quantity value
 * passes through round(), which rounds half away from zero (SPEC 5.1).
 */
final class Decimal
{
    /** Internal precision for intermediate results, well beyond the stored 4 places. */
    public const WORK_SCALE = 12;

    public static function round(string|int $value, int $scale): string
    {
        $value = self::normalise($value);
        $half = '0.'.str_repeat('0', $scale).'5';

        $rounded = str_starts_with($value, '-')
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);

        return self::isZero($rounded) ? bcadd('0', '0', $scale) : $rounded;
    }

    public static function add(string|int $a, string|int $b): string
    {
        return bcadd(self::normalise($a), self::normalise($b), self::WORK_SCALE);
    }

    public static function sub(string|int $a, string|int $b): string
    {
        return bcsub(self::normalise($a), self::normalise($b), self::WORK_SCALE);
    }

    public static function mul(string|int $a, string|int $b): string
    {
        return bcmul(self::normalise($a), self::normalise($b), self::WORK_SCALE);
    }

    public static function div(string|int $a, string|int $b): string
    {
        if (self::isZero($b)) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return bcdiv(self::normalise($a), self::normalise($b), self::WORK_SCALE);
    }

    public static function compare(string|int $a, string|int $b): int
    {
        return bccomp(self::normalise($a), self::normalise($b), self::WORK_SCALE);
    }

    public static function isZero(string|int $value): bool
    {
        return self::compare($value, '0') === 0;
    }

    public static function negate(string|int $value): string
    {
        return self::sub('0', $value);
    }

    private static function normalise(string|int $value): string
    {
        $value = trim((string) $value);

        if (! is_numeric($value) || str_contains(strtolower($value), 'e')) {
            throw new InvalidArgumentException("Not a plain decimal number: [{$value}].");
        }

        return $value;
    }
}
