<?php

namespace App\Enums;

enum StockCountStatus: string
{
    case Draft = 'DRAFT';
    case Counting = 'COUNTING';
    case Posted = 'POSTED';
    case Cancelled = 'CANCELLED';

    public function isFinal(): bool
    {
        return $this === self::Posted || $this === self::Cancelled;
    }
}
