<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

test('responses carry baseline security headers', function () {
    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin');
});

test('the backup writes a gzipped dump and removes dumps past the retention period', function () {
    $dir = storage_path('framework/testing/backups');
    File::deleteDirectory($dir);
    config(['ims.backup.path' => $dir, 'ims.backup.keep_days' => 30]);
    File::ensureDirectoryExists($dir);
    touch($old = $dir.'/ims_test-20200101-000000.sql.gz', now()->subDays(31)->getTimestamp());

    $this->artisan('ims:backup')->expectsOutputToContain('Backup written')->assertSuccessful();

    $files = File::glob($dir.'/*.sql.gz');
    expect($files)->toHaveCount(1)
        ->and(file_exists($old))->toBeFalse()
        ->and(gzdecode(file_get_contents($files[0])))->toContain('CREATE TABLE `stock_transactions`')->toContain('stock_transactions_block_update');

    File::deleteDirectory($dir);
});

test('the scheduler runs the digest hourly and the backup and ledger check nightly', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('ims:send-digests')
        ->expectsOutputToContain('ims:backup')
        ->expectsOutputToContain('ims:verify-stock');
});

test('the Admin menu appears for administrators only', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/')->assertSee('Audit log')->assertSee('Reason codes');
    $this->actingAs(User::factory()->create())->get('/')->assertDontSee('Audit log');
});
