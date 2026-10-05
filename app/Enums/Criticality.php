<?php

namespace App\Enums;

enum Criticality: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    /**
     * Suggested days between cycle counts (SPEC 5.6).
     */
    public function countIntervalDays(): int
    {
        return match ($this) {
            self::A => 30,
            self::B, self::C => 90,
        };
    }
}
