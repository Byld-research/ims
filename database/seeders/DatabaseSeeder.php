<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data, safe to run repeatedly in every environment.
     * Development users are added outside production only.
     */
    public function run(): void
    {
        $this->call([
            SiteSeeder::class,
            ReasonCodeSeeder::class,
            CategorySeeder::class,
            MachineRegisterSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DevUserSeeder::class);
        }
    }
}
