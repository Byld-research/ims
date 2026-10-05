<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export for list views (SPEC 7, principle 2): the same filtered query, all rows, no paging.
 */
final class CsvExport
{
    public static function requested(Request $request): bool
    {
        return $request->query('export') === 'csv';
    }

    /**
     * @param  list<string>  $headings
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $basename, array $headings, iterable $rows): StreamedResponse
    {
        $filename = $basename.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accented supplier names correctly.
            fputcsv($out, $headings, escape: '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralise spreadsheet formula injection in user-entered text.
     */
    private static function cell(mixed $value): string
    {
        $value = match (true) {
            $value instanceof \BackedEnum => (string) $value->value,
            is_bool($value) => $value ? 'yes' : 'no',
            default => (string) $value,
        };

        return preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value) ? "'".$value : $value;
    }
}
