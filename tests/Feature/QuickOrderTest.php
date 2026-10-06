<?php

use App\Enums\PurchaseOrderStatus as S;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\QuickOrder;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;

/*
| SPEC 5.3a and criteria 34–37. Case: Colorado (BPC002) reorders Truss Saw parts for 004C
| from the Kraków warehouse and air filters from a local supplier.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->manager = User::factory()->manager($this->colorado)->create();
    $this->krakow = Supplier::factory()->create(['name' => 'Kraków warehouse']);
    $this->denver = Supplier::factory()->create(['name' => 'Denver Filters']);

    // Blade: min 4, 1 in stock, packs of 5 from Kraków.
    $this->blade = Item::factory()->create(['sku' => 'SP-10001', 'uom' => 'pc']);
    SupplierItem::factory()->for($this->krakow)->for($this->blade)->create(['last_price' => '412.0000', 'pack_size' => 5]);
    $this->bladeStock = qoStock($this->blade, '1', ['min_level' => 4]);

    // Air filter: two-bin, 6 per bin, 5 left, from Denver.
    $this->filter = Item::factory()->create(['sku' => 'CN-20001', 'uom' => 'pc']);
    SupplierItem::factory()->for($this->denver)->for($this->filter)->create(['last_price' => '9.5000', 'pack_size' => 1]);
    $this->filterStock = qoStock($this->filter, '5', ['is_kanban' => true, 'bin_qty' => 6]);
});

function qoStock(Item $item, string $qty, array $levels, ?Site $site = null): Stock
{
    $site ??= test()->colorado;
    $user = User::factory()->admin()->create();
    app(StockService::class)->adjust($item, $site, true, $qty, ReasonCode::adjustment('FOUND'), $user, '1');

    $stock = Stock::query()->where('item_id', $item->id)->where('site_id', $site->id)->sole();
    $stock->update($levels);

    return $stock->fresh(['item', 'site']);
}

function qoRows(array $stocks)
{
    return app(QuickOrder::class)->suggest(collect($stocks))->keyBy(fn ($row) => $row['stock']->item->sku);
}

test('criterion 34: twice the minimum less stock in whole packs, one bin for a two-bin item', function () {
    $rows = qoRows([$this->bladeStock, $this->filterStock]);

    expect($rows['SP-10001']['qty'])->toBe('10.000')
        ->and($rows['SP-10001']['supplier_id'])->toBe($this->krakow->id)
        ->and($rows['SP-10001']['include'])->toBeTrue()
        ->and($rows['CN-20001']['qty'])->toBe('6.000')
        ->and($rows['CN-20001']['supplier_id'])->toBe($this->denver->id);
});

test('criterion 34: an item already on a draft starts unticked and its quantity is subtracted', function () {
    $orders = app(PurchaseOrderService::class);
    $draft = $orders->create($this->krakow, $this->colorado, $this->manager);
    $orders->addLine($draft, $this->blade, '3', null);

    $row = qoRows([$this->bladeStock])['SP-10001'];

    // 2 × 4 − 1 − 3 = 4, rounded up to a pack of 5.
    expect($row['include'])->toBeFalse()
        ->and($row['qty'])->toBe('5.000')
        ->and($row['in_progress'])->toHaveCount(1)
        ->and($row['in_progress'][0]['number'])->toBe($draft->number)
        ->and($row['in_progress'][0]['status'])->toBe(S::Draft);
});

test('suggested quantity never goes below zero and needs no pack size', function () {
    $stock = new Stock(['min_level' => '4', 'is_kanban' => false]);
    $stock->qty = '3';

    expect(QuickOrder::suggestedQty($stock, '10', '5'))->toBe('0.000')
        ->and(QuickOrder::suggestedQty($stock, '0', '0'))->toBe('5.000')
        ->and(QuickOrder::suggestedQty($stock, '0', '2.5'))->toBe('5.000');
});

test('the supplier last ordered from wins over other linked suppliers', function () {
    $other = Supplier::factory()->create(['name' => 'Another']);
    SupplierItem::factory()->for($other)->for($this->blade)->create(['last_price' => '400.0000', 'pack_size' => 1]);

    $orders = app(PurchaseOrderService::class);
    $sent = $orders->create($this->krakow, $this->colorado, $this->manager);
    $orders->addLine($sent, $this->blade, '1', null);
    $orders->markOrdered($sent);
    $sent->lines()->update(['qty_received' => 1]);

    expect(qoRows([$this->bladeStock])['SP-10001']['supplier_id'])->toBe($this->krakow->id);
});

test('criterion 35: items from two suppliers become two drafts with last prices, and stock is unchanged', function () {
    $ledger = StockTransaction::query()->count();

    $this->actingAs($this->manager)
        ->get(route('purchase-orders.quick', ['stocks' => [$this->bladeStock->id, $this->filterStock->id]]))
        ->assertOk()
        ->assertSee('SP-10001')
        ->assertSee('CN-20001');

    $this->post(route('purchase-orders.quick.store'), ['lines' => [
        $this->bladeStock->id => ['include' => '1', 'supplier_id' => $this->krakow->id, 'qty' => '10', 'unit_price' => ''],
        $this->filterStock->id => ['include' => '1', 'supplier_id' => $this->denver->id, 'qty' => '6', 'unit_price' => '9.75'],
    ]])->assertRedirect(route('purchase-orders.index', ['status' => 'DRAFT']));

    $orders = PurchaseOrder::query()->with('lines')->orderBy('id')->get();
    expect($orders)->toHaveCount(2)
        ->and($orders->pluck('status')->unique()->all())->toBe([S::Draft])
        ->and($orders->pluck('site_id')->unique()->all())->toBe([$this->colorado->id])
        ->and($orders[0]->supplier_id)->toBe($this->krakow->id)
        ->and($orders[0]->lines->sole()->qty_ordered)->toBe('10.000')
        ->and($orders[0]->lines->sole()->unit_price)->toBe('412.0000')
        ->and($orders[1]->lines->sole()->unit_price)->toBe('9.7500')
        ->and($orders[0]->notes)->toBe('Created from the dashboard.')
        ->and(StockTransaction::query()->count())->toBe($ledger);
});

test('one supplier gives one draft and opens it; unticked lines are left out', function () {
    $this->actingAs($this->manager)->post(route('purchase-orders.quick.store'), ['lines' => [
        $this->bladeStock->id => ['include' => '1', 'supplier_id' => $this->krakow->id, 'qty' => '10'],
        $this->filterStock->id => ['supplier_id' => $this->denver->id, 'qty' => 'not checked'],
    ]])->assertRedirect(route('purchase-orders.show', PurchaseOrder::query()->sole()));

    expect(PurchaseOrderLine::query()->sole()->item_id)->toBe($this->blade->id);
});

test('a supplier without a last price needs a price, and nothing is created', function () {
    $this->actingAs($this->manager)->post(route('purchase-orders.quick.store'), ['lines' => [
        $this->bladeStock->id => ['include' => '1', 'supplier_id' => $this->krakow->id, 'qty' => '10'],
        $this->filterStock->id => ['include' => '1', 'supplier_id' => $this->krakow->id, 'qty' => '6'],
    ]])->assertSessionHasErrors("lines.{$this->filterStock->id}.unit_price");

    expect(PurchaseOrder::query()->count())->toBe(0);
});

test('nothing ticked creates nothing', function () {
    $this->actingAs($this->manager)->from(route('dashboard'))->post(route('purchase-orders.quick.store'), ['lines' => [
        $this->bladeStock->id => ['supplier_id' => $this->krakow->id, 'qty' => '10'],
    ]])->assertRedirect(route('dashboard'))->assertSessionHas('warning');

    expect(PurchaseOrder::query()->count())->toBe(0);
});

test('criterion 36: a Colorado manager cannot order for Georgia', function () {
    $georgia = qoStock($this->blade, '1', ['min_level' => 2], $this->georgia);

    // The review screen leaves the Georgia line out; posting it directly is refused.
    $this->actingAs($this->manager)
        ->get(route('purchase-orders.quick', ['stocks' => [$georgia->id]]))
        ->assertRedirect(route('dashboard'));

    $this->post(route('purchase-orders.quick.store'), ['lines' => [
        $georgia->id => ['include' => '1', 'supplier_id' => $this->krakow->id, 'qty' => '5'],
    ]])->assertForbidden();

    expect(PurchaseOrder::query()->count())->toBe(0);
});

test('criterion 36: an operator sees no tick boxes and is refused', function () {
    $operator = User::factory()->operator($this->colorado)->create();

    $this->actingAs($operator)->get('/')->assertOk()
        ->assertDontSee('name="stocks[]"', false)
        ->assertDontSee('Order selected');

    $this->get(route('purchase-orders.quick', ['stocks' => [$this->bladeStock->id]]))->assertRedirect(route('dashboard'));
    $this->post(route('purchase-orders.quick.store'), ['lines' => [
        $this->bladeStock->id => ['include' => '1', 'supplier_id' => $this->krakow->id, 'qty' => '5'],
    ]])->assertForbidden();
});

test('criterion 37: the dashboard shows a draft order next to the item, with a tick box for the manager', function () {
    $orders = app(PurchaseOrderService::class);
    $draft = $orders->create($this->krakow, $this->colorado, $this->manager);
    $orders->addLine($draft, $this->blade, '3', null);

    $this->actingAs($this->manager)->get('/')->assertOk()
        ->assertSee($draft->number)
        ->assertSee('Draft · 3')
        ->assertSee('name="stocks[]" value="'.$this->bladeStock->id.'"', false)
        ->assertSee('Order selected');
});

test('orders in progress leave out cancelled orders, closed lines and lines received in full', function () {
    $orders = app(PurchaseOrderService::class);

    $cancelled = $orders->create($this->krakow, $this->colorado, $this->manager);
    $orders->addLine($cancelled, $this->blade, '5', null);
    $orders->cancel($cancelled, $this->manager, 'test');

    $received = $orders->create($this->krakow, $this->colorado, $this->manager);
    $orders->addLine($received, $this->blade, '2', null);
    $orders->markOrdered($received);
    $orders->receive($received, [$received->lines()->sole()->id => '2'], $this->manager);

    expect(PurchaseOrderLine::inProgress([$this->blade->id]))->toBe([]);
});
