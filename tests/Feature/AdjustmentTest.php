<?php

use App\Models\Item;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->manager = User::factory()->manager($this->colorado)->create();
    $this->item = Item::factory()->create(['sku' => 'SP-BLADE', 'uom' => 'pc']);
    $this->opening = ReasonCode::adjustment(ReasonCode::OPENING);
});

function adjustment(array $overrides = []): array
{
    return [
        'site_id' => test()->colorado->id,
        'item_id' => test()->item->id,
        'direction' => 'in',
        'qty' => '4',
        'reason_code_id' => test()->opening->id,
        'unit_cost' => '412.00',
        'note' => 'Opening count, price from Kraków list',
        ...$overrides,
    ];
}

test('a manager records an opening balance and stays on the form for the next item', function () {
    $this->actingAs($this->manager)
        ->post(route('adjustments.store'), adjustment())
        ->assertRedirect(route('adjustments.create', ['site' => $this->colorado->id, 'reason' => $this->opening->id, 'direction' => 'in']))
        ->assertSessionHas('success', 'SP-BLADE at BPC002: +4 pc, now 4.');

    $stock = Stock::query()->where('item_id', $this->item->id)->where('site_id', $this->colorado->id)->sole();
    expect($stock)->qty->toBe('4.000')->avg_cost->toBe('412.0000')
        ->and($stock->last_counted_at)->not->toBeNull()
        ->and(StockTransaction::query()->sole())->note->toBe('Opening count, price from Kraków list')->user_id->toBe($this->manager->id);
});

test('the form names the reason and keeps it for the next entry', function () {
    $this->actingAs($this->manager)
        ->get(route('adjustments.create', ['reason' => $this->opening->id]))
        ->assertOk()
        ->assertSee('BPC002 · BPC Colorado')
        ->assertSee('<option value="'.$this->opening->id.'" selected', false);
});

test('a decrease larger than stock is refused with the available quantity', function () {
    $this->actingAs($this->manager)->post(route('adjustments.store'), adjustment(['qty' => '2']));

    $this->post(route('adjustments.store'), adjustment(['direction' => 'out', 'qty' => '3', 'reason_code_id' => ReasonCode::adjustment('DAMAGE')->id]))
        ->assertSessionHasErrors(['qty' => 'Only 2 pc available at BPC002.']);

    expect(StockTransaction::query()->count())->toBe(1);
});

test('criterion 5 through the form: no cost into empty stock is refused', function () {
    $this->actingAs($this->manager)
        ->post(route('adjustments.store'), adjustment(['unit_cost' => '']))
        ->assertSessionHasErrors(['qty' => 'Enter a unit cost: this item has no average cost yet at BPC002.']);
});

test('quantities are positive; the direction carries the sign', function (string $qty) {
    $this->actingAs($this->manager)
        ->post(route('adjustments.store'), adjustment(['qty' => $qty]))
        ->assertSessionHasErrors('qty');
})->with(['-4', '0', '0.000', '1.2345', 'four']);

test('only adjustment reason codes are accepted', function () {
    $this->actingAs($this->manager)
        ->post(route('adjustments.store'), adjustment(['reason_code_id' => ReasonCode::query()->where('code', 'MAINTENANCE')->value('id')]))
        ->assertSessionHasErrors('reason_code_id');
});

test('a manager cannot adjust the other site', function () {
    $this->actingAs($this->manager)
        ->post(route('adjustments.store'), adjustment(['site_id' => $this->georgia->id]))
        ->assertForbidden();

    expect(StockTransaction::query()->count())->toBe(0);
});

test('an operator cannot open the adjustment form', function () {
    $this->actingAs(User::factory()->operator($this->colorado)->create())
        ->get(route('adjustments.create'))
        ->assertForbidden();
});

test('an administrator chooses the site explicitly', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('adjustments.create'))->assertOk()->assertSee('BPC001 · BPC Georgia')->assertSee('BPC002 · BPC Colorado');
    $this->post(route('adjustments.store'), adjustment(['site_id' => $this->georgia->id]))->assertSessionHasNoErrors();

    expect(Stock::query()->where('site_id', $this->georgia->id)->value('qty'))->toBe('4.000');
});

test('the stock lookup returns quantity and whether a cost exists', function () {
    $this->actingAs($this->manager)->post(route('adjustments.store'), adjustment(['qty' => '2.5']));

    $this->getJson(route('stock.lookup', ['item' => $this->item->id, 'site' => $this->colorado->id]))
        ->assertOk()
        ->assertJson(['qty' => '2.500', 'has_cost' => true, 'site' => 'BPC002', 'item' => ['uom' => 'pc']]);

    $this->getJson(route('stock.lookup', ['item' => $this->item->id, 'site' => $this->georgia->id]))
        ->assertJson(['qty' => '0.000', 'has_cost' => false]);
});
