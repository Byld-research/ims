<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Item;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\PurchaseOrder;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockCount;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\StockCountService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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

        $this->openingBalances();
        $this->purchaseOrders();
        $this->stockCounts();
    }

    /**
     * Colorado: a posted class A count from two weeks ago that found one blade missing, and a
     * consumables count in progress. Skipped when any count exists.
     */
    private function stockCounts(): void
    {
        if (StockCount::query()->exists()) {
            return;
        }

        $counts = app(StockCountService::class);
        $user = User::query()->where('role', Role::Admin)->firstOrFail();
        $colorado = Site::query()->where('code', 'BPC002')->firstOrFail();
        $ids = fn (array $skus) => Item::query()->whereIn('sku', $skus)->pluck('id', 'sku');

        Carbon::setTestNow(now()->subDays(14)->setTime(15, 0));
        $classA = $counts->create($colorado, $user, 'Class A monthly');
        $counts->addItems($classA, $ids(['SP-10001', 'SP-10002', 'SP-10004', 'WP-20001'])->values()->all());
        $counts->startCounting($classA);
        $lines = $classA->lines()->with('item')->get()->keyBy('item.sku');
        $counts->recordCounts($classA, [
            $lines['SP-10001']->id => ['qty' => Stock::query()->where('site_id', $colorado->id)->where('item_id', $lines['SP-10001']->item_id)->value('qty') - 1, 'note' => 'One blade missing from the rack'],
            $lines['SP-10002']->id => ['qty' => Stock::query()->where('site_id', $colorado->id)->where('item_id', $lines['SP-10002']->item_id)->value('qty')],
            $lines['SP-10004']->id => ['qty' => Stock::query()->where('site_id', $colorado->id)->where('item_id', $lines['SP-10004']->item_id)->value('qty')],
            $lines['WP-20001']->id => ['qty' => null],
        ]);
        $counts->post($classA, $user);
        Carbon::setTestNow();

        $consumables = $counts->create($colorado, $user, 'Consumables quarterly');
        $counts->addItems($consumables, $ids(['CS-30001', 'CS-30002', 'CS-30003', 'CS-30004'])->values()->all());
        $counts->startCounting($consumables);
    }

    /**
     * Orders in every interesting state, mostly around the Truss Saw 004C in Colorado.
     * Skipped when any order exists, so re-running adds nothing.
     */
    private function purchaseOrders(): void
    {
        if (PurchaseOrder::query()->exists()) {
            return;
        }

        $orders = app(PurchaseOrderService::class);
        $user = User::query()->where('role', Role::Admin)->firstOrFail();
        $sites = Site::query()->pluck('id', 'code');
        $colorado = Site::query()->findOrFail($sites['BPC002']);
        $georgia = Site::query()->findOrFail($sites['BPC001']);
        $supplier = fn (string $name) => Supplier::query()->where('name', $name)->firstOrFail();
        $item = fn (string $sku) => Item::query()->where('sku', $sku)->firstOrFail();

        // Colorado, Kraków: confirmed, ETA already passed, nothing received (a dashboard alert).
        $late = $orders->create($supplier('Kraków warehouse'), $colorado, $user, 'Blades and light curtain for 004C.');
        $orders->addLine($late, $item('SP-10001'), '4', null);
        $orders->addLine($late, $item('SP-10009'), '1', null);
        $orders->markOrdered($late);
        $orders->confirm($late, now()->subDays(5));

        // Colorado, Grainger: shipped, then partly received.
        $partial = $orders->create($supplier('Grainger'), $colorado, $user);
        $orders->addLine($partial, $item('CS-30001'), '12', null);
        $orders->addLine($partial, $item('SP-10004'), '2', null);
        $orders->markOrdered($partial);
        $orders->confirm($partial, now()->addDays(2));
        $orders->ship($partial, 'https://www.ups.com/track?tracknum=1Z999AA10123456784');
        $orders->receive($partial->fresh(), [$partial->lines()->where('item_id', $item('CS-30001')->id)->value('id') => '6'], $user);

        // Colorado, McMaster-Carr: sent, never confirmed.
        $unconfirmed = $orders->create($supplier('McMaster-Carr'), $colorado, $user);
        $orders->addLine($unconfirmed, $item('SP-10003'), '2', null);
        $orders->markOrdered($unconfirmed);

        // Georgia: a draft still being prepared.
        $draft = $orders->create($supplier('Kraków warehouse'), $georgia, $user, 'Spares for 003C (rev 1.0).');
        $orders->addLine($draft, $item('SP-10011'), '1', null);
    }

    /**
     * Consumption over the last 120 days, so machine histories and the dashboard have data.
     * [days ago, site, sku, qty, machine SKU or general-issue reason code]
     */
    private const CONSUMPTION = [
        [100, 'BPC002', 'SP-10001', 1, '004C'], [70, 'BPC002', 'SP-10001', 1, '004C'], [40, 'BPC002', 'SP-10001', 1, '004C'], [12, 'BPC002', 'SP-10001', 1, '004C'],
        [100, 'BPC002', 'CS-30001', 2, '004C'], [80, 'BPC002', 'CS-30001', 2, '004C'], [60, 'BPC002', 'CS-30001', 2, '004C'],
        [40, 'BPC002', 'CS-30001', 2, '004C'], [20, 'BPC002', 'CS-30001', 2, '004C'], [5, 'BPC002', 'CS-30001', 2, '004C'],
        [90, 'BPC002', 'CS-30003', 2, '004C'], [60, 'BPC002', 'CS-30003', 2, '004C'], [30, 'BPC002', 'CS-30003', 2, '004C'], [25, 'BPC002', 'CS-30003', 2, 'MAINTENANCE'],
        [85, 'BPC002', 'WP-20001', 1, '003A'], [50, 'BPC002', 'WP-20001', 1, '003A'], [15, 'BPC002', 'WP-20001', 1, '003A'],
        [60, 'BPC002', 'CS-30002', 20, '003A'],
        [75, 'BPC002', 'CS-30004', 50, '008W'], [20, 'BPC002', 'CS-30004', 40, '009W'],
        [95, 'BPC001', 'SP-10001', 1, '003C'], [45, 'BPC001', 'SP-10001', 1, '003C'],
        [90, 'BPC001', 'CS-30001', 2, '003C'], [50, 'BPC001', 'CS-30001', 2, '003C'], [10, 'BPC001', 'CS-30001', 2, '003C'],
        [30, 'BPC001', 'SP-10004', 1, '003C'],
        [60, 'BPC001', 'WP-20002', 1, '004A'], [20, 'BPC001', 'WP-20002', 1, '005A'],
        [40, 'BPC001', 'CS-30002', 20, '004A'],
        [70, 'BPC002', 'CS-30005', 1, '004C'], [45, 'BPC002', 'CS-30005', 2, '004C'], [21, 'BPC002', 'CS-30005', 1, '004C'], [6, 'BPC002', 'CS-30005', 2, '004C'],
        [33, 'BPC001', 'CS-30004', 30, '004B'], [15, 'BPC001', 'CS-30004', 20, 'FACILITY'],
    ];

    /**
     * Opening balances 120 days ago through StockService (reason OPENING, cost from the supplier
     * price), then the consumption above, then one transfer. Opening quantities are the final
     * quantities plus what was consumed, so the final state is as listed. Some items are
     * deliberately short. Skipped once any movement exists, so re-running adds nothing.
     */
    private function openingBalances(): void
    {
        if (StockTransaction::query()->exists()) {
            return;
        }

        $user = User::query()->where('role', Role::Admin)->first()
            ?? User::query()->firstOrCreate(['email' => 'demo-data@bpc.test'], [
                'name' => 'Demo data', 'password' => Str::random(40), 'role' => Role::Admin, 'is_active' => false,
            ]);
        $opening = ReasonCode::adjustment(ReasonCode::OPENING);
        $service = app(StockService::class);
        $sites = Site::query()->orderBy('code')->get()->keyBy('code');
        $item = fn (string $sku) => Item::query()->where('sku', $sku)->firstOrFail();

        // sku => [BPC001 Georgia final qty, BPC002 Colorado final qty, min level (one value, or [Georgia, Colorado]), kanban bin qty]
        // Revision-specific saw parts get a minimum only where that revision runs: 1.0 in Georgia, 2.0 in Colorado.
        $levels = [
            'SP-10001' => [2, 2, 2, null],      // saw blade: a count finds one missing in Colorado, leaving 1 (short)
            'SP-10002' => [4, 6, 4, null],      // linear bearing: one moves to Colorado, leaving Georgia short
            'SP-10003' => [2, 0, 1, null],      // timing belt: Colorado out
            'SP-10004' => [3, 4, 2, null],
            'SP-10005' => [0, 1, [0, 1], null], // servo cable, rev 2.0 only (004C in Colorado)
            'SP-10009' => [0, 0, [0, 1], null], // light curtain, rev 2.0: out in Colorado
            'SP-10011' => [1, 0, [1, 0], null], // stepper drive, rev 1.0 only (003C in Georgia)
            'SP-10012' => [2, 0, [1, 0], null],
            'WP-20001' => [6, 3, 4, null],
            'WP-20002' => [4, 4, 4, null],
            'CS-30001' => [12, 0, null, 6],     // filters: Colorado empty; the Grainger order delivers one bin (6)
            'CS-30002' => [80, 120, null, 60],  // hydraulic oil in litres
            'CS-30003' => [20, 8, null, 10],
            'CS-30004' => [400, 250, null, 100],
            'TL-40002' => [1, 1, null, null],
            'CS-30005' => [6, 9, null, null],  // O-ring kits: used every few weeks on 004C, no minimum yet
        ];

        $consumed = [];
        foreach (self::CONSUMPTION as [, $siteCode, $sku, $qty]) {
            $consumed[$siteCode][$sku] = ($consumed[$siteCode][$sku] ?? 0) + $qty;
        }

        Carbon::setTestNow(now()->subDays(120)->setTime(7, 30));

        foreach ($sites->values() as $index => $site) {
            foreach ($levels as $sku => [$georgia, $colorado, $min, $binQty]) {
                $qty = ($index === 0 ? $georgia : $colorado) + ($consumed[$site->code][$sku] ?? 0);

                if ($qty > 0) {
                    $price = $item($sku)->supplierItems()->orderByDesc('last_price')->value('last_price') ?? '1.0000';
                    $service->adjust($item($sku), $site, true, (string) $qty, $opening, $user, $price,
                        'Opening balance; cost from the supplier price list');
                }

                Stock::query()->updateOrCreate(['item_id' => $item($sku)->id, 'site_id' => $site->id], [
                    'min_level' => (is_array($min) ? $min[$index] : $min) ?? 0,
                    'is_kanban' => $binQty !== null,
                    'bin_qty' => $binQty,
                    'bin' => sprintf('%s-%s', $index === 0 ? 'GA' : 'CO', substr($sku, 0, 2).substr($sku, -2)),
                ]);
            }
        }

        $events = collect(self::CONSUMPTION)->sortByDesc(fn ($event) => $event[0])->values();
        $machines = Machine::query()->get()->keyBy('sku');

        foreach ($events as $n => [$daysAgo, $siteCode, $sku, $qty, $destination]) {
            Carbon::setTestNow(Carbon::now('UTC')->setTimestamp(time())->subDays($daysAgo)->setTime(13 + $n % 6, ($n * 7) % 60));

            if (isset($machines[$destination])) {
                $service->issueToMachine($item($sku), $machines[$destination], (string) $qty, $user);
            } else {
                $service->issueGeneral($item($sku), $sites[$siteCode], (string) $qty,
                    ReasonCode::query()->where('code', $destination)->firstOrFail(), $user, 'Workshop and building upkeep');
            }
        }

        // A linear bearing moved from Georgia to Colorado for 004C.
        Carbon::setTestNow(Carbon::now('UTC')->setTimestamp(time())->subDays(35)->setTime(16, 10));
        $service->transfer($item('SP-10002'), $sites['BPC001'], $sites['BPC002'], '1', $user, 'Carried over for 004C carriage repair');
        $service->adjust($item('SP-10002'), $sites['BPC002'], false, '1', ReasonCode::adjustment('DAMAGE'), $user, null, 'Old bearing found cracked on arrival check');

        Carbon::setTestNow();
    }
}
