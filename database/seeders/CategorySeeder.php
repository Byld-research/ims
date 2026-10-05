<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Structural parents with assignable children (SPEC 9).
     */
    public function run(): void
    {
        $tree = [
            'Spare Parts' => ['Mechanical', 'Hydraulic', 'Pneumatic', 'Electrical', 'Electronic & Optics'],
            'Wear Parts' => ['Punches', 'Die Blades', 'Dies & Forming'],
            'Consumables' => ['Cutting', 'Filtration', 'Lubricants & Fluids', 'Fasteners', 'Seals & Gaskets'],
            'Tools' => ['Power Tools', 'Hand Tools', 'Measuring & Gauges'],
            'Machines' => ['Production Machines'],
        ];

        foreach ($tree as $parentName => $children) {
            $parent = Category::query()->firstOrCreate(
                ['parent_id' => null, 'name' => $parentName],
                ['is_structural' => true],
            );

            foreach ($children as $childName) {
                Category::query()->firstOrCreate(
                    ['parent_id' => $parent->id, 'name' => $childName],
                    ['is_structural' => false],
                );
            }
        }
    }
}
