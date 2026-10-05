<?php

namespace Tests\Concurrency;

/**
 * Starts worker.php processes in parallel and collects their JSON results.
 */
final class Concurrency
{
    /**
     * @param  list<list<string>>  $jobs  arguments for worker.php, without the start time
     * @return list<array{ok: bool, result?: string, error?: string}>
     */
    public static function run(array $jobs): array
    {
        $startAt = sprintf('%.6F', microtime(true) + 1.5);
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mariadb',
            'DB_DATABASE' => 'ims_test',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'PATH' => getenv('PATH'),
            'HOME' => getenv('HOME'),
        ];

        $processes = [];
        foreach ($jobs as $job) {
            $command = [PHP_BINARY, __DIR__.'/worker.php', ...$job, $startAt];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            $processes[] = [$process, $pipes];
        }

        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            proc_close($process);
            $lines = array_values(array_filter(explode(PHP_EOL, trim($out))));
            $results[] = json_decode(end($lines) ?: 'null', true) ?? ['ok' => false, 'error' => trim($out."\n".$err)];
        }

        return $results;
    }
}
