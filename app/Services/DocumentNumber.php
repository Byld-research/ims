<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Document numbers per prefix and year: PO-2026-0001, SC-2026-0001 (SPEC 5.4).
 *
 * One atomic statement increments the counter and hands its value back through LAST_INSERT_ID,
 * so concurrent callers can never receive the same number. A rolled-back caller leaves a gap,
 * which the specification accepts.
 */
class DocumentNumber
{
    public function next(string $prefix, int $year): string
    {
        DB::statement(
            'INSERT INTO number_sequences (prefix, year, last_value) VALUES (?, ?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)',
            [$prefix, $year],
        );

        $value = (int) DB::selectOne('SELECT LAST_INSERT_ID() AS value')->value;

        return sprintf('%s-%d-%04d', $prefix, $year, $value);
    }
}
