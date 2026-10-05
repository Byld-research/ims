<?php

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $sites = [
            ['code' => 'BPC001', 'name' => 'BPC Georgia', 'state' => 'GA', 'timezone' => 'America/New_York'],
            ['code' => 'BPC002', 'name' => 'BPC Colorado', 'state' => 'CO', 'timezone' => 'America/Denver'],
        ];

        foreach ($sites as $site) {
            Site::query()->firstOrCreate(['code' => $site['code']], $site);
        }
    }
}
