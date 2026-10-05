<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Proves a backup can be restored (SPEC 9a): loads it into a scratch database, checks that every
 * table and the ledger triggers came back and that stock still equals a replay of the ledger,
 * then drops the scratch database. Run before go-live and after any change to the backup set-up.
 */
#[Signature('ims:restore-test {file? : Backup file; defaults to the newest one}')]
#[Description('Restore a backup into a scratch database and verify it')]
class RestoreTest extends Command
{
    public function handle(StockService $stock): int
    {
        $live = config('database.default');
        $db = config("database.connections.{$live}");
        $file = $this->argument('file') ?? collect(File::glob(config('ims.backup.path').'/*.sql.gz'))->sortByDesc(fn ($f) => filemtime($f))->first();

        if (! $file || ! is_file($file)) {
            $this->error('No backup file found.');

            return self::FAILURE;
        }

        $scratch = config('ims.backup.restore_test_database');
        if ($scratch === $db['database']) {
            $this->error('The restore test database must not be the live database.');

            return self::FAILURE;
        }

        $this->line("Restoring {$file} into {$scratch}…");
        DB::statement("DROP DATABASE IF EXISTS `{$scratch}`");
        DB::statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        try {
            $restore = Process::fromShellCommandline(
                'set -o pipefail; gunzip -c "$IN" | mariadb --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" "$DB_NAME"',
                null,
                ['IN' => $file, 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_USER' => $db['username'],
                    'MYSQL_PWD' => (string) $db['password'], 'DB_NAME' => $scratch],
                null,
                3600,
            );
            $restore->run();

            if (! $restore->isSuccessful()) {
                $this->error('Restore failed: '.trim($restore->getErrorOutput()));

                return self::FAILURE;
            }

            config(['database.connections.restore_test' => [...$db, 'database' => $scratch]]);
            DB::purge('restore_test');
            $restored = DB::connection('restore_test');

            $tables = collect($restored->select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0]);
            $expected = collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0]);
            $missing = $expected->diff($tables);
            $triggers = collect($restored->select("SHOW TRIGGERS LIKE 'stock_transactions'"))->count();

            $this->table(['Check', 'Result'], [
                ['Tables restored', $tables->count().' of '.$expected->count().($missing->isEmpty() ? '' : ' (missing: '.$missing->join(', ').')')],
                ['Ledger triggers', $triggers.' of 2'],
                ['Ledger rows', $restored->table('stock_transactions')->count()],
                ['Stock rows', $restored->table('stocks')->count()],
            ]);

            // Replay the restored ledger against the restored stock rows.
            DB::setDefaultConnection('restore_test');
            $mismatches = 0;
            foreach (Stock::query()->with(['item', 'site'])->get() as $row) {
                $replayed = $stock->replay($row->item, $row->site);
                $mismatches += (int) ($replayed['qty'] !== $row->qty || $replayed['avg_cost'] !== $row->avg_cost);
            }
            DB::setDefaultConnection($live);

            $problems = array_filter([
                $missing->isEmpty() ? null : 'tables missing: '.$missing->join(', '),
                $triggers === 2 ? null : "ledger triggers missing ({$triggers} of 2): the ledger would no longer be append-only",
                $mismatches === 0 ? null : "{$mismatches} stock rows differ from a replay of the ledger",
            ]);

            if ($problems) {
                $this->error('Restore test FAILED: '.implode('; ', $problems).'.');

                return self::FAILURE;
            }

            $this->info('Restore test passed: the backup is complete and consistent.');

            return self::SUCCESS;
        } finally {
            DB::setDefaultConnection($live);
            DB::statement("DROP DATABASE IF EXISTS `{$scratch}`");
        }
    }
}
