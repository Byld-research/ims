<?php

use App\Enums\PurchaseOrderStatus as S;
use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Database\Seeders\DatabaseSeeder;

/*
| SPEC 5.3, 5.4 and criteria 10–15, 28. Case: Colorado (BPC002) orders Truss Saw parts for 004C
| from the Kraków warehouse.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->manager = User::factory()->manager($this->colorado)->create();
    $this->krakow = Supplier::factory()->create(['name' => 'Kraków warehouse']);
    $this->blade = Item::factory()->create(['sku' => 'SP-10001', 'uom' => 'pc']);
    $this->servo = Item::factory()->create(['sku' => 'SP-10005', 'uom' => 'pc']);
    SupplierItem::factory()->for($this->krakow)->for($this->blade)->create(['last_price' => '412.0000', 'pack_size' => 1]);
    SupplierItem::factory()->for($this->krakow)->for($this->servo)->create(['last_price' => '238.0000', 'pack_size' => 5]);

    $this->actingAs($this->manager);
});

/** A draft at Colorado with 10 blades at 5.00 (criteria 10–13 use 10 units). */
function draftOrder(string $qty = '10', string $price = '5.00'): PurchaseOrder
{
    test()->post(route('purchase-orders.store'), ['supplier_id' => test()->krakow->id, 'site_id' => test()->colorado->id]);
    $order = PurchaseOrder::query()->latest('id')->firstOrFail();
    test()->post(route('purchase-orders.lines.store', $order), ['item_id' => test()->blade->id, 'qty_ordered' => $qty, 'unit_price' => $price]);

    return $order->fresh();
}

function sentOrder(string $qty = '10', string $price = '5.00'): PurchaseOrder
{
    $order = draftOrder($qty, $price);
    test()->post(route('purchase-orders.order', $order));

    return $order->fresh();
}

function receive(PurchaseOrder $order, array $quantities)
{
    return test()->post(route('purchase-orders.receive.store', $order), ['lines' => $quantities]);
}

function bladeStock(): ?Stock
{
    return Stock::query()->where('item_id', test()->blade->id)->where('site_id', test()->colorado->id)->first();
}

test('a manager creates a numbered draft for their own site', function () {
    $this->post(route('purchase-orders.store'), ['supplier_id' => $this->krakow->id, 'site_id' => $this->colorado->id])
        ->assertRedirect();

    $order = PurchaseOrder::query()->sole();
    expect($order->number)->toBe('PO-'.now('America/Denver')->year.'-0001')
        ->and($order->status)->toBe(S::Draft)
        ->and($order->created_by)->toBe($this->manager->id);
});

test('criterion 15: orders in the same year get consecutive numbers', function () {
    $orders = app(PurchaseOrderService::class);
    $numbers = collect(range(1, 3))->map(fn () => $orders->create($this->krakow, $this->colorado, $this->manager)->number);

    $year = now('America/Denver')->year;
    expect($numbers->all())->toBe(["PO-{$year}-0001", "PO-{$year}-0002", "PO-{$year}-0003"]);
});

test('numbering restarts each calendar year', function () {
    $orders = app(PurchaseOrderService::class);

    $this->travelTo('2026-12-31 12:00:00');
    expect($orders->create($this->krakow, $this->colorado, $this->manager)->number)->toBe('PO-2026-0001');

    $this->travelTo('2027-01-02 12:00:00');
    expect($orders->create($this->krakow, $this->colorado, $this->manager)->number)->toBe('PO-2027-0001');
});

test('a manager cannot order for the other site', function () {
    $this->post(route('purchase-orders.store'), ['supplier_id' => $this->krakow->id, 'site_id' => $this->georgia->id])
        ->assertForbidden();
});

test('an empty price takes the supplier’s last price; no last price means the price is required', function () {
    $order = draftOrder();
    $this->post(route('purchase-orders.lines.store', $order), ['item_id' => $this->servo->id, 'qty_ordered' => '2']);

    expect($order->lines()->where('item_id', $this->servo->id)->value('unit_price'))->toBe('238.0000');

    $unknown = Item::factory()->create();
    $this->post(route('purchase-orders.lines.store', $order), ['item_id' => $unknown->id, 'qty_ordered' => '1'])
        ->assertSessionHasErrors(['unit_price' => 'Enter a price: Kraków warehouse has no last price for '.$unknown->sku.'.']);
});

test('lines can change only while the order is a draft', function () {
    $order = sentOrder();
    $line = $order->lines()->sole();

    $this->post(route('purchase-orders.lines.store', $order), ['item_id' => $this->servo->id, 'qty_ordered' => '1', 'unit_price' => '1'])
        ->assertSessionHasErrors('order');
    $this->put(route('purchase-order-lines.update', $line), ['qty_ordered' => '99', 'unit_price' => '1'])->assertSessionHasErrors('order');
    $this->delete(route('purchase-order-lines.destroy', $line))->assertSessionHasErrors('order');

    expect($line->fresh()->qty_ordered)->toBe('10.000');
});

test('an order without lines cannot be sent', function () {
    $this->post(route('purchase-orders.store'), ['supplier_id' => $this->krakow->id, 'site_id' => $this->colorado->id]);
    $order = PurchaseOrder::query()->sole();

    $this->post(route('purchase-orders.order', $order))->assertSessionHasErrors(['order' => 'Add at least one line before sending the order.']);
});

test('the happy path: sent, confirmed with ETA, shipped with tracking, received, closed', function () {
    $order = sentOrder();
    $today = now('America/Denver')->toDateString();

    expect($order->status)->toBe(S::Ordered)->and($order->ordered_at->toDateString())->toBe($today)
        ->and(SupplierItem::query()->where('item_id', $this->blade->id)->value('last_price'))->toBe('5.0000');

    $this->post(route('purchase-orders.confirm', $order), ['eta' => '2026-11-20']);
    expect($order->fresh())->status->toBe(S::Confirmed)->eta->toDateString()->toBe('2026-11-20');

    $this->post(route('purchase-orders.ship', $order), ['tracking_ref' => 'https://track.example.com/1Z999']);
    expect($order->fresh())->status->toBe(S::Shipped)->tracking_ref->toBe('https://track.example.com/1Z999');

    receive($order, [$order->lines()->value('id') => '10'])->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe(S::Received);

    $this->post(route('purchase-orders.close', $order));
    expect($order->fresh())->status->toBe(S::Closed)->closed_at->not->toBeNull();
});

test('steps cannot be skipped or repeated', function () {
    $order = draftOrder();

    $this->post(route('purchase-orders.confirm', $order), ['eta' => '2026-11-20'])->assertSessionHasErrors(['order' => 'An order that is draft cannot become confirmed.']);
    $this->post(route('purchase-orders.close', $order))->assertSessionHasErrors('order');

    $this->post(route('purchase-orders.order', $order));
    $this->post(route('purchase-orders.order', $order))->assertSessionHasErrors('order');
    $this->post(route('purchase-orders.ship', $order), ['tracking_ref' => 'X'])->assertSessionHasErrors('order');
});

test('confirming needs an ETA and shipping needs a tracking reference', function () {
    $order = sentOrder();

    $this->post(route('purchase-orders.confirm', $order), [])->assertSessionHasErrors('eta');
    $this->post(route('purchase-orders.confirm', $order), ['eta' => '2026-11-20']);
    $this->post(route('purchase-orders.ship', $order), ['tracking_ref' => ''])->assertSessionHasErrors('tracking_ref');
});

test('criteria 10 and 11: receive 6 of 10, then the remaining 4', function () {
    $order = sentOrder();
    $line = $order->lines()->sole();

    receive($order, [$line->id => '6'])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(S::PartiallyReceived)
        ->and($line->fresh())->qty_received->toBe('6.000')->is_closed->toBeFalse()
        ->and($line->fresh()->outstanding())->toBe('4.000')
        ->and(bladeStock())->qty->toBe('6.000')->avg_cost->toBe('5.0000');

    receive($order, [$line->id => '4'])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(S::Received)
        ->and(bladeStock()->qty)->toBe('10.000')
        ->and(StockTransaction::query()->where('type', TransactionType::Receipt)->count())->toBe(2);
});

test('criterion 1 through a real receipt: 10 at 5.00 into empty stock', function () {
    $order = sentOrder('10', '5.00');
    receive($order, [$order->lines()->value('id') => '10']);

    $receipt = StockTransaction::query()->sole();
    expect(bladeStock())->qty->toBe('10.000')->avg_cost->toBe('5.0000')
        ->and($receipt)->type->toBe(TransactionType::Receipt)->unit_cost->toBe('5.0000')->value->toBe('50.0000')
        ->purchase_order_line_id->toBe($order->lines()->value('id'))->site_id->toBe($this->colorado->id);
});

test('criterion 2 through receipts: a second order at 7.00 averages to 6.0000', function () {
    $first = sentOrder('10', '5.00');
    receive($first, [$first->lines()->value('id') => '10']);
    $second = sentOrder('10', '7.00');
    receive($second, [$second->lines()->value('id') => '10']);

    expect(bladeStock())->qty->toBe('20.000')->avg_cost->toBe('6.0000');
});

test('criterion 12: closing the remaining 4 short also completes the order, without a stock movement', function () {
    $order = sentOrder();
    $line = $order->lines()->sole();
    receive($order, [$line->id => '6']);

    $this->post(route('purchase-order-lines.close-short', $line), ['reason' => 'Discontinued by the supplier'])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(S::Received)
        ->and($line->fresh()->is_closed)->toBeTrue()
        ->and(StockTransaction::query()->count())->toBe(1)
        ->and(bladeStock()->qty)->toBe('6.000')
        ->and($order->fresh()->notes)->toContain('Line SP-10001 closed short, 4 pc not delivered: Discontinued by the supplier');
});

test('closing short needs a reason', function () {
    $order = sentOrder();

    $this->post(route('purchase-order-lines.close-short', $order->lines()->sole()), ['reason' => ''])->assertSessionHasErrors('reason');
});

test('criterion 13: receiving 12 against 10 succeeds, records 12 and warns about pack size', function () {
    $order = sentOrder();
    $line = $order->lines()->sole();

    receive($order, [$line->id => '12'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('warning', fn (string $w) => str_contains($w, 'SP-10001 (+2 pc)') && str_contains($w, 'pack-size'));

    expect($line->fresh()->qty_received)->toBe('12.000')
        ->and($order->fresh()->status)->toBe(S::Received)
        ->and(bladeStock()->qty)->toBe('12.000');
});

test('criterion 14: an order with a received line cannot be cancelled', function () {
    $order = sentOrder();
    receive($order, [$order->lines()->value('id') => '1']);

    $this->post(route('purchase-orders.cancel', $order), ['reason' => 'Changed our mind'])->assertSessionHasErrors('order');

    expect($order->fresh()->status)->toBe(S::PartiallyReceived);
});

test('an order with nothing received can be cancelled, with a reason in the notes', function () {
    $order = sentOrder();

    $this->post(route('purchase-orders.cancel', $order), ['reason' => 'Found two at Georgia; transfer instead'])->assertSessionHasNoErrors();

    expect($order->fresh())->status->toBe(S::Cancelled)
        ->notes->toContain('Cancelled: Found two at Georgia; transfer instead');
});

test('criterion 28: a confirmed order can be received in full and goes straight to RECEIVED', function () {
    $order = sentOrder();
    $this->post(route('purchase-orders.confirm', $order), ['eta' => '2026-11-20']);

    receive($order, [$order->lines()->value('id') => '10'])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(S::Received);
});

test('goods arriving without any confirmation can be received on an ORDERED order', function () {
    $order = sentOrder();

    receive($order, [$order->lines()->value('id') => '3'])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(S::PartiallyReceived);
});

test('drafts, received, closed and cancelled orders refuse receipts', function (S $status) {
    $order = PurchaseOrder::factory()->status($status)->create(['site_id' => $this->colorado->id, 'supplier_id' => $this->krakow->id]);
    $line = PurchaseOrderLine::factory()->for($order)->for($this->blade)->create();

    receive($order, [$line->id => '1'])->assertSessionHasErrors('lines');

    expect(StockTransaction::query()->count())->toBe(0);
})->with([S::Draft, S::Received, S::Closed, S::Cancelled]);

test('a line closed short refuses further receipts', function () {
    $order = draftOrder();
    $this->post(route('purchase-orders.lines.store', $order), ['item_id' => $this->servo->id, 'qty_ordered' => '2']);
    $this->post(route('purchase-orders.order', $order));
    $blade = $order->lines()->where('item_id', $this->blade->id)->sole();
    $this->post(route('purchase-order-lines.close-short', $blade), ['reason' => 'Not needed']);

    receive($order, [$blade->id => '1'])->assertSessionHasErrors(['lines' => 'Line SP-10001 was closed short and cannot receive more.']);
});

test('a receipt needs at least one positive quantity', function () {
    $order = sentOrder();

    receive($order, [$order->lines()->value('id') => ''])->assertSessionHasErrors('lines');
    receive($order, [$order->lines()->value('id') => '-2'])->assertSessionHasErrors('lines.'.$order->lines()->value('id'));
});

test('a manager cannot receive at the other site', function () {
    $order = PurchaseOrder::factory()->status(S::Ordered)->create(['site_id' => $this->georgia->id, 'supplier_id' => $this->krakow->id]);
    $line = PurchaseOrderLine::factory()->for($order)->for($this->blade)->create();

    receive($order, [$line->id => '1'])->assertForbidden();
    $this->get(route('purchase-orders.receive', $order))->assertForbidden();
});

test('operators read orders but cannot act on them', function () {
    $order = sentOrder();
    $operator = User::factory()->operator($this->colorado)->create();

    $this->actingAs($operator)->get(route('purchase-orders.index'))->assertOk()->assertSee($order->number);
    $this->actingAs($operator)->get(route('purchase-orders.show', $order))->assertOk()->assertDontSee(__('Receive goods'));
    $this->actingAs($operator)->post(route('purchase-orders.confirm', $order), ['eta' => '2026-11-20'])->assertForbidden();
});

test('the list is scoped to the site, filterable, and exports CSV', function () {
    $order = sentOrder('2', '412');
    $other = PurchaseOrder::factory()->create(['site_id' => $this->georgia->id, 'number' => 'PO-GEORGIA-1']);

    $this->get(route('purchase-orders.index'))->assertSee($order->number)->assertDontSee('PO-GEORGIA-1');
    $this->get(route('purchase-orders.index', ['status' => 'DRAFT']))->assertDontSee($order->number);
    $this->get(route('purchase-orders.index', ['status' => 'open']))->assertSee($order->number);

    expect($this->get(route('purchase-orders.index', ['export' => 'csv']))->streamedContent())
        ->toContain($order->number)
        ->toContain('"Kraków warehouse",ORDERED')
        ->toContain('824.0000');
});

test('the order page shows lines, details and receipts; dates stay editable', function () {
    $order = sentOrder();
    receive($order, [$order->lines()->value('id') => '4']);

    $this->get(route('purchase-orders.show', $order))
        ->assertOk()
        ->assertSee('SP-10001')
        ->assertSee('Partially received')
        ->assertSee('Close short…')
        ->assertSee('Receipts');

    $this->put(route('purchase-orders.update', $order), ['ordered_at' => '2026-10-01', 'eta' => '2026-10-30', 'notes' => 'Called Kraków'])
        ->assertSessionHasNoErrors();
    expect($order->fresh())->ordered_at->toDateString()->toBe('2026-10-01')->notes->toBe('Called Kraków');
});

test('quantities on open orders are shown next to stock, never added to it', function () {
    $order = sentOrder('10');
    receive($order, [$order->lines()->value('id') => '6']);

    expect(PurchaseOrderLine::onOrder([$this->blade->id]))->toBe([$this->blade->id => [$this->colorado->id => '4.000']])
        ->and(bladeStock()->qty)->toBe('6.000');

    $this->get(route('items.show', $this->blade))->assertSee('On open orders')->assertSee('4 outstanding');
});

test('several lines are received in one go', function () {
    $order = draftOrder();
    $this->post(route('purchase-orders.lines.store', $order), ['item_id' => $this->servo->id, 'qty_ordered' => '2']);
    $this->post(route('purchase-orders.order', $order));
    [$blade, $servo] = $order->lines()->orderBy('id')->get()->all();

    receive($order, [$blade->id => '6', $servo->id => '3'])->assertSessionHasNoErrors();

    expect($blade->fresh()->qty_received)->toBe('6.000')
        ->and($servo->fresh()->qty_received)->toBe('3.000')
        ->and($order->fresh()->status)->toBe(S::PartiallyReceived);
});

test('the over-receipt warning and success message are shown on the order page', function () {
    $order = sentOrder();

    $this->followingRedirects()
        ->post(route('purchase-orders.receive.store', $order), ['lines' => [$order->lines()->value('id') => '12']])
        ->assertOk()
        ->assertSee('Receipt recorded for 1 line.')
        ->assertSee('More than ordered was received for SP-10001 (+2 pc)');
});
