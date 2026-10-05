<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Development accounts only: one administrator and one manager per site.
 * Passwords come from SEED_ADMIN_PASSWORD and SEED_MANAGER_PASSWORD.
 */
class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevUserSeeder must not run in production.');
        }

        $adminPassword = config('ims.seed.admin_password');
        $managerPassword = config('ims.seed.manager_password');

        if (! $adminPassword || ! $managerPassword) {
            $this->command?->warn('SEED_ADMIN_PASSWORD or SEED_MANAGER_PASSWORD not set; skipping development users.');

            return;
        }

        User::query()->firstOrCreate(
            ['email' => config('ims.seed.admin_email')],
            ['name' => 'Administrator', 'password' => $adminPassword, 'role' => Role::Admin, 'site_id' => null],
        );

        foreach (Site::query()->orderBy('code')->get() as $site) {
            User::query()->firstOrCreate(
                ['email' => 'manager.'.strtolower($site->code).'@bpc.test'],
                ['name' => $site->code.' Manager', 'password' => $managerPassword, 'role' => Role::Manager, 'site_id' => $site->id],
            );
        }
    }
}
