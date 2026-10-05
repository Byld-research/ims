<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\MachineType;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demonstration catalogue for development and training (SPEC 9), built around type C,
 * the Truss Saw. Never run in production.
 * Run with: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoDataSeeder must not run in production.');
        }

        $this->call(DatabaseSeeder::class);

        $suppliers = collect([
            ['name' => 'Kraków warehouse', 'contact_email' => 'warehouse.krk@example.com', 'lead_time_days' => 28,
                'notes' => 'Group warehouse. Ships dedicated machine parts; customs clearance adds about a week.'],
            ['name' => 'Grainger', 'contact_email' => 'orders@grainger.example.com', 'lead_time_days' => 3],
            ['name' => 'McMaster-Carr', 'contact_email' => 'sales@mcmaster.example.com', 'lead_time_days' => 2],
            ['name' => 'Fastenal', 'contact_email' => 'denver@fastenal.example.com', 'lead_time_days' => 1],
            ['name' => 'Hytrol Parts Direct', 'contact_email' => 'parts@hytrol.example.com', 'lead_time_days' => 14],
        ])->mapWithKeys(fn (array $s) => [$s['name'] => Supplier::query()->firstOrCreate(['name' => $s['name']], $s)]);

        $category = fn (string $name) => Category::query()->where('name', $name)->where('is_structural', false)->firstOrFail()->id;

        // [sku, name, category, uom, criticality, manufacturer, mpn, supplier, price, pack]
        $items = [
            ['SP-10001', 'Saw blade 18" carbide, 72T', 'Cutting', 'pc', 'A', 'Leitz', 'WK-18-72', 'Kraków warehouse', '412.0000', 1],
            ['SP-10002', 'Linear guide bearing block', 'Mechanical', 'pc', 'A', 'Hiwin', 'HGH25CA', 'Kraków warehouse', '186.5000', 1],
            ['SP-10003', 'Timing belt HTD 8M-30', 'Mechanical', 'pc', 'B', 'Gates', '8MGT-1200-30', 'McMaster-Carr', '74.2000', 1],
            ['SP-10004', 'Proximity sensor M18 PNP', 'Electrical', 'pc', 'A', 'Sick', 'IME18-08BPSZC0S', 'Grainger', '96.8000', 1],
            ['SP-10005', 'Servo drive cable 10 m', 'Electrical', 'pc', 'A', 'Siemens', '6FX5002-5CS01-1BA0', 'Kraków warehouse', '238.0000', 1],
            ['SP-10006', 'Pneumatic cylinder 50x100', 'Pneumatic', 'pc', 'B', 'Festo', 'DSBC-50-100-PPVA-N3', 'Kraków warehouse', '321.4000', 1],
            ['SP-10007', 'Solenoid valve 5/2 24VDC', 'Pneumatic', 'pc', 'B', 'Festo', 'VUVG-L14-M52-MT-G18-1R8L', 'Grainger', '142.0000', 1],
            ['SP-10008', 'Hydraulic hose 1/2" x 1 m', 'Hydraulic', 'pc', 'C', 'Parker', '471TC-8', 'Grainger', '38.9000', 1],
            ['SP-10009', 'Light curtain receiver', 'Electronic & Optics', 'pc', 'A', 'Sick', 'C4P-EA15031A00', 'Kraków warehouse', '1280.0000', 1],
            ['WP-20001', 'Nail plate press punch', 'Punches', 'pc', 'A', null, 'DRW-4471', 'Kraków warehouse', '265.0000', 1],
            ['WP-20002', 'Die blade, upper', 'Die Blades', 'pc', 'A', null, 'DRW-4472', 'Kraków warehouse', '198.0000', 1],
            ['CS-30001', 'Air filter element', 'Filtration', 'pc', 'C', 'Donaldson', 'P181052', 'Grainger', '41.3000', 6],
            ['CS-30002', 'Hydraulic oil ISO VG 46', 'Lubricants & Fluids', 'l', 'C', 'Mobil', 'DTE 25', 'Grainger', '6.1500', 20],
            ['CS-30003', 'Grease cartridge EP2', 'Lubricants & Fluids', 'pc', 'C', 'SKF', 'LGEP 2/0.4', 'Fastenal', '17.8000', 10],
            ['CS-30004', 'Hex bolt M10x40 8.8 zinc', 'Fasteners', 'pc', 'C', null, null, 'Fastenal', '0.3100', 100],
            ['CS-30005', 'O-ring kit, metric', 'Seals & Gaskets', 'set', 'C', 'Parker', 'ORK-METRIC', 'McMaster-Carr', '54.0000', 1],
            ['TL-40001', 'Cordless impact driver 18V', 'Power Tools', 'pc', null, 'DeWalt', 'DCF887', 'Grainger', '189.0000', 1],
            ['TL-40002', 'Torque wrench 20-100 Nm', 'Hand Tools', 'pc', null, 'Wera', '05075610001', 'McMaster-Carr', '246.0000', 1],
            ['TL-40003', 'Digital caliper 150 mm', 'Measuring & Gauges', 'pc', null, 'Mitutoyo', '500-196-30', 'McMaster-Carr', '128.0000', 1],
            ['SP-10010', 'Conveyor roller 1.9" x 24"', 'Mechanical', 'pc', 'B', 'Hytrol', 'R-19-24', 'Hytrol Parts Direct', '32.7500', 1],
            ['SP-10011', 'Stepper drive, saw axis (rev 1.0)', 'Electrical', 'pc', 'A', 'Leadshine', 'DM860H', 'Kraków warehouse', '214.0000', 1],
            ['SP-10012', 'Stepper motor cable 8 m', 'Electrical', 'pc', 'A', 'Leadshine', 'CABLE-M-8', 'Kraków warehouse', '61.0000', 1],
        ];

        foreach ($items as [$sku, $name, $categoryName, $uom, $criticality, $manufacturer, $mpn, $supplier, $price, $pack]) {
            $item = Item::query()->firstOrCreate(['sku' => $sku], [
                'name' => $name, 'category_id' => $category($categoryName), 'uom' => $uom,
                'criticality' => $criticality, 'manufacturer' => $manufacturer, 'mpn' => $mpn,
            ]);

            $suppliers[$supplier]->supplierItems()->firstOrCreate(['item_id' => $item->id], [
                'supplier_sku' => $supplier === 'Kraków warehouse' ? $mpn : null,
                'last_price' => $price,
                'pack_size' => $pack,
            ]);
        }

        // A second source for a few items, so price comparison is visible.
        foreach (['SP-10004' => '104.5000', 'SP-10007' => '151.2000'] as $sku => $price) {
            $suppliers['McMaster-Carr']->supplierItems()->firstOrCreate(
                ['item_id' => Item::query()->where('sku', $sku)->value('id')],
                ['last_price' => $price, 'pack_size' => 1],
            );
        }

        // Type C, the Truss Saw, is the reference case (SPEC 9): lines common to both revisions,
        // plus lines that differ between 003C (1.0) and 004C (2.0).
        // [sku, revision (null = all), reference, qty per machine, consumable]
        $parts = [
            'C' => [
                ['SP-10001', null, 'Main blade', 1, true],
                ['SP-10002', null, 'Carriage', 4, false],
                ['SP-10003', null, 'Feed drive', 2, false],
                ['SP-10004', null, 'Home position', 3, false],
                ['SP-10011', '1.0', 'Axis 1 drive', 1, false],
                ['SP-10012', '1.0', 'Axis 1', 1, false],
                ['SP-10005', '2.0', 'Axis 1', 1, false],
                ['SP-10009', '2.0', 'Guarding', 1, false],
                ['CS-30001', null, 'Dust extraction', 2, true],
                ['CS-30003', null, 'Lubrication points', null, true],
            ],
            'A' => [
                ['WP-20001', null, 'Press head', 2, true],
                ['WP-20002', null, 'Die set', 2, true],
                ['SP-10006', null, 'Clamp', 2, false],
                ['SP-10007', null, 'Clamp valve', 1, false],
                ['SP-10008', null, 'Hydraulic unit', 4, false],
                ['CS-30002', null, 'Hydraulic unit', 60, true],
            ],
            'W' => [
                ['CS-30004', null, 'Frame', null, true],
                ['SP-10010', null, 'Infeed rollers', 12, false],
            ],
        ];

        foreach ($parts as $code => $lines) {
            $type = MachineType::query()->where('code', $code)->firstOrFail();

            foreach ($lines as [$sku, $revision, $reference, $qty, $consumable]) {
                $type->partsList()->firstOrCreate(
                    ['item_id' => Item::query()->where('sku', $sku)->value('id'), 'revision' => $revision],
                    ['reference' => $reference, 'qty_per_machine' => $qty, 'is_consumable' => $consumable],
                );
            }
        }
    }
}
