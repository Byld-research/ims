<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('creates the first administrator with a password asked for without echo', function () {
    $this->artisan('ims:create-admin', ['email' => 'Admin@ByldInc.com'])
        ->expectsQuestion('Password (not shown)', 'Inventory2026x')
        ->expectsQuestion('Repeat the password', 'Inventory2026x')
        ->expectsOutputToContain('Administrator admin@byldinc.com created')
        ->assertSuccessful();

    $user = User::query()->where('email', 'admin@byldinc.com')->sole();
    expect($user->role)->toBe(Role::Admin)->and($user->site_id)->toBeNull()
        ->and(Hash::check('Inventory2026x', $user->password))->toBeTrue();
});

test('resets an existing administrator, refuses mismatches and non-admins', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@byldinc.com']);
    User::factory()->create(['email' => 'manager@byldinc.com']);

    $this->artisan('ims:create-admin', ['email' => 'admin@byldinc.com'])
        ->expectsQuestion('Password (not shown)', 'Inventory2026x')
        ->expectsQuestion('Repeat the password', 'different-2026x')
        ->assertFailed();

    $this->artisan('ims:create-admin', ['email' => 'admin@byldinc.com'])
        ->expectsQuestion('Password (not shown)', 'NewPassword2027')
        ->expectsQuestion('Repeat the password', 'NewPassword2027')
        ->expectsOutputToContain('Password reset')
        ->assertSuccessful();
    expect(Hash::check('NewPassword2027', $admin->fresh()->password))->toBeTrue();

    $this->artisan('ims:create-admin', ['email' => 'manager@byldinc.com'])->assertFailed();
});
