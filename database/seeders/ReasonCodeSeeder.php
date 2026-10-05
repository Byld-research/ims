<?php

namespace Database\Seeders;

use App\Enums\ReasonCodeScope;
use App\Models\ReasonCode;
use Illuminate\Database\Seeder;

class ReasonCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            [ReasonCodeScope::Adjustment, ReasonCode::COUNT, 'Cycle count correction'],
            [ReasonCodeScope::Adjustment, 'DAMAGE', 'Damaged'],
            [ReasonCodeScope::Adjustment, 'LOSS', 'Lost or missing'],
            [ReasonCodeScope::Adjustment, 'FOUND', 'Found, not recorded'],
            [ReasonCodeScope::Adjustment, ReasonCode::OPENING, 'Opening balance'],
            [ReasonCodeScope::Adjustment, 'SCRAP', 'Scrapped'],
            [ReasonCodeScope::IssueGeneral, 'MAINTENANCE', 'General maintenance'],
            [ReasonCodeScope::IssueGeneral, 'FACILITY', 'Facility and building'],
            [ReasonCodeScope::IssueGeneral, 'SAMPLE', 'Testing or sample'],
            [ReasonCodeScope::IssueGeneral, 'OTHER', 'Other, see note'],
        ];

        foreach ($codes as [$scope, $code, $label]) {
            ReasonCode::query()->firstOrCreate(
                ['applies_to' => $scope, 'code' => $code],
                ['label' => $label],
            );
        }
    }
}
