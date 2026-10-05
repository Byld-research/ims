<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Machine;
use App\Models\MachineType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Imports a machine type's parts list from CSV (SPEC 10: "import from spreadsheet").
 *
 * Columns, header row required: sku (required), revision, reference, qty_per_machine, is_consumable, note.
 * An empty revision means the line applies to every revision of the type (SPEC 5.8).
 * Rows are matched to the catalogue by SKU and upserted by item and revision. The import is
 * all-or-nothing: any invalid row rejects the whole file, so a half-imported list never exists.
 */
class PartsListImporter
{
    public const COLUMNS = ['sku', 'revision', 'reference', 'qty_per_machine', 'is_consumable', 'note'];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @return array{created: int, updated: int}|null null when the file was rejected; see errors()
     */
    public function import(MachineType $machineType, string $path): ?array
    {
        $this->errors = [];
        $rows = $this->parse($path);

        if (! $this->errors) {
            $this->checkAgainstExistingList($machineType, $rows);
        }

        if ($this->errors) {
            return null;
        }

        return DB::transaction(function () use ($machineType, $rows) {
            $created = $updated = 0;

            foreach ($rows as $row) {
                $line = $machineType->partsList()->updateOrCreate(
                    ['item_id' => $row['item_id'], 'revision' => $row['revision']],
                    Arr::except($row, 'line'),
                );
                $line->wasRecentlyCreated ? $created++ : $updated++;
            }

            return compact('created', 'updated');
        });
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '');

        if (! $header) {
            $this->errors[] = __('The file is empty.');

            return [];
        }

        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);

        if (! in_array('sku', $header, true)) {
            $this->errors[] = __('The header row must contain a "sku" column. Allowed columns: :columns.', ['columns' => implode(', ', self::COLUMNS)]);

            return [];
        }

        $records = [];
        $line = 1;

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $line++;

            if ($values === [null] || trim(implode('', $values)) === '') {
                continue;
            }

            $records[$line] = array_combine(
                $header,
                array_slice(array_pad(array_map('trim', $values), count($header), ''), 0, count($header)),
            );
        }

        fclose($handle);

        if (! $records) {
            $this->errors[] = __('The file contains no data rows.');

            return [];
        }

        $items = Item::query()
            ->whereIn('sku', array_column($records, 'sku'))
            ->get(['id', 'sku', 'is_active'])
            ->keyBy('sku');

        $rows = [];
        $seen = [];

        foreach ($records as $line => $record) {
            $sku = $record['sku'];
            $item = $items->get($sku);

            if ($sku === '') {
                $this->errors[] = __('Line :line: the SKU is empty.', ['line' => $line]);

                continue;
            }

            if (! $item) {
                $this->errors[] = __('Line :line: no item with SKU :sku.', ['line' => $line, 'sku' => $sku]);

                continue;
            }

            if (! $item->is_active) {
                $this->errors[] = __('Line :line: item :sku is inactive.', ['line' => $line, 'sku' => $sku]);

                continue;
            }

            $revision = ($record['revision'] ?? '') ?: null;

            if ($revision !== null && ! preg_match(Machine::REVISION_PATTERN, $revision)) {
                $this->errors[] = __('Line :line: revision ":revision" must look like 2.0, or be empty for all revisions.', ['line' => $line, 'revision' => $revision]);

                continue;
            }

            $key = $sku.'|'.$revision;

            if (isset($seen[$key])) {
                $this->errors[] = __('Line :line: SKU :sku :revision already appears on line :first.', [
                    'line' => $line, 'sku' => $sku, 'revision' => $revision ? __('for revision :r', ['r' => $revision]) : __('for all revisions'), 'first' => $seen[$key],
                ]);

                continue;
            }

            $seen[$key] = $line;
            $qty = $record['qty_per_machine'] ?? '';

            if ($qty !== '' && ! preg_match('/^\d{1,11}(\.\d{1,3})?$/', $qty)) {
                $this->errors[] = __('Line :line: quantity ":qty" is not a positive number with at most 3 decimals.', ['line' => $line, 'qty' => $qty]);

                continue;
            }

            if (mb_strlen($record['reference'] ?? '') > 80 || mb_strlen($record['note'] ?? '') > 255) {
                $this->errors[] = __('Line :line: reference (80) or note (255) is too long.', ['line' => $line]);

                continue;
            }

            $rows[] = [
                'line' => $line,
                'item_id' => $item->id,
                'revision' => $revision,
                'reference' => ($record['reference'] ?? '') ?: null,
                'qty_per_machine' => $qty !== '' && bccomp($qty, '0', 3) > 0 ? $qty : null,
                'is_consumable' => in_array(strtolower($record['is_consumable'] ?? ''), ['1', 'yes', 'y', 'true', 'x'], true),
                'note' => ($record['note'] ?? '') ?: null,
            ];
        }

        return $rows;
    }

    /**
     * Apply the all-revisions-or-specific rule (SPEC 5.8) to the file and to lines already on the list.
     * A file line replaces the existing line with the same item and revision, so that one is ignored.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function checkAgainstExistingList(MachineType $machineType, array $rows): void
    {
        $existing = $machineType->partsList()->get(['item_id', 'revision'])
            ->groupBy('item_id')
            ->map(fn ($lines) => $lines->pluck('revision')->all());

        $byItem = collect($rows)->groupBy('item_id');

        foreach ($byItem as $itemId => $fileRows) {
            $revisions = collect($existing->get($itemId, []))
                ->merge($fileRows->pluck('revision'))
                ->unique(fn ($r) => $r ?? "\0");

            if ($revisions->contains(null) && $revisions->count() > 1) {
                $first = $fileRows->first();
                $this->errors[] = __('Line :line: SKU :sku would be listed both for all revisions and for specific revisions (:revisions). Use one or the other.', [
                    'line' => $first['line'],
                    'sku' => Item::query()->whereKey($itemId)->value('sku'),
                    'revisions' => $revisions->filter()->join(', '),
                ]);
            }
        }
    }
}
