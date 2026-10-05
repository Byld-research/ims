<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Nightly database dump, gzipped, kept for BACKUP_KEEP_DAYS days (SPEC 9a).
 * Includes triggers: they make the ledger append-only, so a restore without them would not be safe.
 */
#[Signature('ims:backup')]
#[Description('Dump the database to the backup folder and remove dumps older than the retention period')]
class Backup extends Command
{
    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));
        $dir = config('ims.backup.path');
        File::ensureDirectoryExists($dir, 0700);

        $file = $dir.'/'.$db['database'].'-'.now()->utc()->format('Ymd-His').'.sql.gz';

        $dump = Process::fromShellCommandline(
            'set -o pipefail; mariadb-dump --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --single-transaction --routines --triggers --events --hex-blob "$DB_NAME" | gzip -9 > "$OUT"',
            null,
            ['DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_USER' => $db['username'],
                'MYSQL_PWD' => (string) $db['password'], 'DB_NAME' => $db['database'], 'OUT' => $file],
            null,
            3600,
        );
        $dump->run();

        if (! $dump->isSuccessful() || ! is_file($file) || filesize($file) < 100) {
            @unlink($file);
            $this->error('Backup failed: '.trim($dump->getErrorOutput()));

            return self::FAILURE;
        }

        chmod($file, 0600);
        $this->info('Backup written: '.$file.' ('.number_format(filesize($file) / 1024, 1).' KB)');

        $cutoff = now()->subDays(config('ims.backup.keep_days'))->getTimestamp();
        foreach (File::glob($dir.'/'.$db['database'].'-*.sql.gz') as $old) {
            if (filemtime($old) < $cutoff) {
                File::delete($old);
                $this->line('Removed old backup: '.basename($old));
            }
        }

        return self::SUCCESS;
    }
}
