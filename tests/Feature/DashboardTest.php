<?php

use App\Enums\PurchaseOrderStatus as S;
use App\Models\Item;
use App\Models\Machine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Dashboard;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
| SPEC 8, with Colorado (BPC002) and its Truss Saw 004C as the case.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->manager = User::factory()->manager($this->colorado)->create();
    $this->stock = app(StockService::class);
    $this->found = ReasonCode::adjustment('FOUND');
    $this->saw004 = Machine::query()->where('sku', '004C')->sole();
    $this->supplier = Supplier::factory()->create(['name' => 'Kraków warehouse']);
});

function stocked(string $sku, string $qty, string $cost, array $levels = [], ?string $criticality = null, ?Site $site = null): Item
{
    $site ??= test()->colorado;
    $item = Item::query()->where('sku', $sku)->first() ?? Item::factory()->create(['sku' => $sku, 'criticality' => $criticality, 'uom' => 'pc']);
    if (bccomp($qty, '0', 3) > 0) {
        test()->stock->adjust($item, $site, true, $qty, test()->found, test()->manager, $cost);
    }
    Stock::query()->updateOrCreate(['item_id' => $item->id, 'site_id' => $site->id], $levels);

    return $item;
}

function dashboard(?Site $site = null): Dashboard
{
    return new Dashboard($site ?? test()->colorado);
}

test('alerts split below minimum, out of stock and kanban refills, class A first', function () {
    stocked('LOW-C', '1', '1', ['min_level' => 3], 'C');
    stocked('LOW-A', '1', '1', ['min_level' => 3], 'A');
    stocked('OUT-1', '0', '1', ['min_level' => 2], 'B');
    stocked('KB-1', '6', '1', ['is_kanban' => true, 'bin_qty' => 6]);
    stocked('OK-1', '9', '1', ['min_level' => 3]);
    stocked('GA-LOW', '1', '1', ['min_level' => 3], 'A', $this->georgia);

    $alerts = dashboard()->alerts();

    expect($alerts['below']['rows']->pluck('item.sku')->all())->toBe(['LOW-A', 'LOW-C'])
        ->and($alerts['out']['rows']->pluck('item.sku')->all())->toBe(['OUT-1'])
        ->and($alerts['kanban']['rows']->pluck('item.sku')->all())->toBe(['KB-1'])
        ->and((new Dashboard(null))->alerts()['below']['total'])->toBe(3); // consolidated adds Georgia
});

test('order alerts: past ETA with nothing received, never confirmed, partly received for over 30 days', function () {
    $order = fn (S $status, array $attributes = []) => PurchaseOrder::factory()->status($status)
        ->create(['site_id' => $this->colorado->id, 'supplier_id' => $this->supplier->id, ...$attributes]);

    $late = $order(S::Confirmed, ['eta' => now()->subDays(3)]);
    $order(S::Confirmed, ['eta' => now()->addDays(3)]);
    $lateButStarted = $order(S::Shipped, ['eta' => now()->subDays(3)]);
    PurchaseOrderLine::factory()->for($lateButStarted)->create(['qty_received' => 1]);
    $unconfirmed = $order(S::Ordered, ['ordered_at' => now()->subDays(9)]);

    $stalled = $order(S::PartiallyReceived);
    $line = PurchaseOrderLine::factory()->for($stalled)->create();
    $this->travelTo(now()->subDays(40));
    DB::transaction(fn () => $this->stock->receipt($line, $this->colorado, '1', $this->manager));
    $this->travelBack();

    $recent = $order(S::PartiallyReceived);
    $recentLine = PurchaseOrderLine::factory()->for($recent)->create();
    DB::transaction(fn () => $this->stock->receipt($recentLine, $this->colorado, '1', $this->manager));

    $alerts = dashboard()->alerts();

    expect($alerts['late']['rows']->pluck('id')->all())->toBe([$late->id])
        ->and($alerts['unconfirmed']['rows']->pluck('id')->all())->toBe([$unconfirmed->id])
        ->and($alerts['stalled']['rows']->pluck('id')->all())->toBe([$stalled->id]);
});

test('figures: value, value by category group, minimum coverage', function () {
    stocked('SP-A', '2', '100', ['min_level' => 3]);
    stocked('SP-B', '10', '5', ['min_level' => 1]);
    stocked('SP-C', '4', '2.5');

    expect(dashboard()->totalValue())->toBe('260.0000000')
        ->and(dashboard()->minimumCoverage())->toBe(['with_minimum' => 2, 'below' => 1])
        ->and(dashboard()->valueByCategory()->sum(fn ($row) => (float) $row->value))->toBe(260.0);
});

test('consumption compares this calendar month with the last, in the site’s time zone', function () {
    $blade = stocked('SP-10001', '10', '400');

    $this->travelTo(now('America/Denver')->startOfMonth()->subDays(5)->setTime(12, 0));
    $this->stock->issueToMachine($blade, $this->saw004, '2', $this->manager);
    $this->travelBack();
    $this->stock->issueToMachine($blade, $this->saw004, '1', $this->manager);
    $this->stock->issueGeneral($blade, $this->colorado, '1', ReasonCode::query()->where('code', 'MAINTENANCE')->sole(), $this->manager);

    expect(dashboard()->consumption())->toMatchArray(['current' => '800.0000', 'previous' => '800.0000']);
});

test('top machines by 90-day consumption value; older issues drop out', function () {
    $blade = stocked('SP-10001', '10', '400');
    $grease = stocked('CS-30003', '50', '17.8');
    $wallMachine = Machine::query()->where('sku', '003A')->sole();

    $this->travelTo(now()->subDays(120));
    $this->stock->issueToMachine($blade, $wallMachine, '5', $this->manager);
    $this->travelBack();
    $this->stock->issueToMachine($blade, $this->saw004, '1', $this->manager);
    $this->stock->issueToMachine($grease, $wallMachine, '2', $this->manager);

    $top = dashboard()->topMachines();

    expect($top->pluck('sku')->all())->toBe(['004C', '003A'])
        ->and($top->first()->value)->toBe('400.0000')
        ->and((int) $top->last()->issues)->toBe(1);
});

test('items in stock with no movement for 12 months', function () {
    $this->travelTo(now()->subMonths(13));
    $old = stocked('OLD-1', '3', '50');
    $this->travelBack();
    stocked('NEW-1', '3', '50');

    $dormant = dashboard()->dormant();

    expect($dormant['count'])->toBe(1)
        ->and($dormant['rows']->pluck('item.sku')->all())->toBe(['OLD-1']);
});

test('the dashboard renders alerts and figures for the manager’s site', function () {
    stocked('SP-10001', '1', '412', ['min_level' => 2], 'A');
    $blade = Item::query()->where('sku', 'SP-10001')->sole();
    $this->stock->issueToMachine($blade, $this->saw004, '1', $this->manager);

    $this->actingAs($this->manager)->get('/')
        ->assertOk()
        ->assertSee('Out of stock')
        ->assertSee('SP-10001')
        ->assertSee('Stock value')
        ->assertSee('Top machines by consumption')
        ->assertSee('004C · Truss Saw 2.0')
        ->assertSee('$412.00');
});

test('with nothing to act on, every status tile reads all clear', function () {
    $response = $this->actingAs($this->manager)->get('/')->assertOk();

    expect(substr_count($response->getContent(), 'All clear'))->toBe(5);
    $response->assertSee('Every item with a minimum is above it.')->assertSee('No late or stuck orders.');
});

test('stock to act on is ordered worst first: out of stock, class A, then least left', function () {
    stocked('LOW-C-HALF', '1', '1', ['min_level' => 2], 'C');
    stocked('LOW-C-TENTH', '1', '1', ['min_level' => 10], 'C');
    stocked('LOW-A', '3', '1', ['min_level' => 4], 'A');
    stocked('OUT-B', '0', '1', ['min_level' => 1], 'B');
    stocked('KANBAN', '2', '1', ['is_kanban' => true, 'bin_qty' => 5], 'C');

    $rows = dashboard()->attention()['rows'];

    expect($rows->pluck('stock.item.sku')->all())->toBe(['OUT-B', 'LOW-A', 'LOW-C-TENTH', 'KANBAN', 'LOW-C-HALF'])
        ->and($rows->pluck('severity')->all())->toBe(['critical', 'serious', 'warning', 'warning', 'warning']);
});

test('the most used items over 30 days, including movers nobody set a minimum for', function () {
    $blade = stocked('SP-10001', '10', '400', ['min_level' => 2]);
    $orings = stocked('CS-30005', '20', '27', []);
    $this->stock->issueToMachine($blade, $this->saw004, '1', $this->manager);
    $this->stock->issueToMachine($orings, $this->saw004, '3', $this->manager);

    $movers = dashboard()->movers();

    expect($movers->pluck('stock.item.sku')->all())->toBe(['SP-10001', 'CS-30005'])
        ->and($movers->last()['qty'])->toBe('3.000');

    $this->actingAs($this->manager)->get('/')
        ->assertSee('Most used in the last 30 days')
        ->assertSee('No minimum set')
        ->assertSee('Set a minimum');
});

test('weekly usage covers twelve weeks, oldest first', function () {
    $filter = stocked('CS-30001', '50', '41.3');
    $this->travelTo(now()->subWeeks(5)->subDay());
    $this->stock->issueToMachine($filter, $this->saw004, '4', $this->manager);
    $this->travelBack();
    $this->stock->issueToMachine($filter, $this->saw004, '2', $this->manager);

    $series = dashboard()->weeklyUsage([$filter->id])[$filter->id.':'.$this->colorado->id];

    expect($series)->toHaveCount(12)
        ->and($series[11])->toBe(2.0)
        ->and($series[6])->toBe(4.0)
        ->and(array_sum($series))->toBe(6.0);
});

test('administrators see the consolidated view with site codes', function () {
    stocked('GA-LOW', '1', '1', ['min_level' => 3], 'A', $this->georgia);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('site.select'), ['site' => 'all']);

    $this->get('/')->assertOk()->assertSee('All sites')->assertSee('GA-LOW')->assertSee('· BPC001');
});
