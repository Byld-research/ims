<?php

use App\Enums\StockCountStatus as Status;
use App\Models\Category;
use App\Models\Item;
use App\Models\Machine;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockCount;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;

/*
| Screen 12, through HTTP. Case: Colorado (BPC002) counts the Truss Saw spares for 004C.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->manager = User::factory()->manager($this->colorado)->create();
    $this->category = Category::factory()->create(['name' => 'Cutting']);
    $this->blade = Item::factory()->for($this->category)->create(['sku' => 'SP-10001', 'uom' => 'pc', 'criticality' => 'HIGH']);
    $this->filter = Item::factory()->create(['sku' => 'CS-30001', 'uom' => 'pc', 'criticality' => 'LOW']);

    $found = ReasonCode::adjustment('FOUND');
    app(StockService::class)->adjust($this->blade, $this->colorado, true, '4', $found, $this->manager, '412');
    app(StockService::class)->adjust($this->filter, $this->colorado, true, '10', $found, $this->manager, '41.3');
    Stock::query()->where('item_id', $this->blade->id)->update(['bin' => 'CO-B2']);
    Stock::query()->where('item_id', $this->filter->id)->update(['bin' => 'CO-A1']);

    $this->actingAs($this->manager);
});

function newCount(): StockCount
{
    test()->post(route('stock-counts.store'), ['site_id' => test()->colorado->id, 'scope_note' => 'Truss Saw spares']);

    return StockCount::query()->latest('id')->firstOrFail();
}

test('a manager creates a count and adds items by category, class, due date and single item', function () {
    $count = newCount();
    expect($count->status)->toBe(Status::Draft)->and($count->scope_note)->toBe('Truss Saw spares');

    $this->post(route('stock-counts.lines.store', $count), ['by' => 'category', 'category_id' => $this->category->parent_id])
        ->assertSessionHas('success', '1 item added.');
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'criticality', 'criticality' => 'LOW'])
        ->assertSessionHas('success', '1 item added.');
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'due'])
        ->assertSessionHas('success', 'No new items: they are all on the count already.');
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'item', 'item_id' => $this->blade->id])
        ->assertSessionHas('success', 'No new items: they are all on the count already.');

    expect($count->lines()->count())->toBe(2);
});

test('the count sheet is sorted by bin and hides expected quantities until asked', function () {
    $count = newCount();
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'due']);
    $this->post(route('stock-counts.start', $count));

    $this->get(route('stock-counts.show', $count))
        ->assertOk()
        ->assertSeeInOrder(['CO-A1', 'CS-30001', 'CO-B2', 'SP-10001'])
        ->assertSee('Show expected quantities')
        ->assertSee('x-show="showExpected"', false);
});

test('the full flow: count, review against live stock, post', function () {
    $count = newCount();
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'due']);
    $this->post(route('stock-counts.start', $count));
    $lines = $count->lines()->get()->keyBy('item_id');

    $this->put(route('stock-counts.counts', $count), ['lines' => [
        $lines[$this->blade->id]->id => ['qty' => '3', 'note' => 'One blade chipped, scrapped earlier'],
        $lines[$this->filter->id]->id => ['qty' => ''],
    ], 'review' => 1])->assertRedirect(route('stock-counts.review', $count));

    // A blade issued to 004C while the count was being reviewed.
    app(StockService::class)->issueToMachine($this->blade, Machine::query()->where('sku', '004C')->sole(), '1', $this->manager);

    $this->get(route('stock-counts.review', $count))
        ->assertOk()
        ->assertSee('Stock of 1 item moved since it was added to the count.')
        ->assertSeeInOrder(['SP-10001', '4', '3', '3', 'none'])
        ->assertSee('not counted');

    $this->post(route('stock-counts.post', $count))
        ->assertRedirect(route('stock-counts.show', $count))
        ->assertSessionHas('success', 'Count posted: 0 adjusted, 1 confirmed unchanged, 1 not counted.');

    expect($count->fresh()->status)->toBe(Status::Posted)
        ->and(Stock::query()->where('item_id', $this->blade->id)->value('qty'))->toBe('3.000');
});

test('criterion 17 through HTTP: a short count posts a COUNT adjustment shown on the count', function () {
    $count = newCount();
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'item', 'item_id' => $this->blade->id]);
    $this->post(route('stock-counts.start', $count));
    $this->put(route('stock-counts.counts', $count), ['lines' => [$count->lines()->value('id') => ['qty' => '2']]]);
    $this->post(route('stock-counts.post', $count));

    expect(StockTransaction::query()->where('stock_count_id', $count->id)->value('qty_delta'))->toBe('-2.000');

    $this->get(route('stock-counts.show', $count))->assertSee('Adjustments posted')->assertSee('Cycle count correction')->assertSee('−2');
});

test('criterion 18 through HTTP: a posted count refuses changes and a second post', function () {
    $count = newCount();
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'item', 'item_id' => $this->blade->id]);
    $this->post(route('stock-counts.start', $count));
    $line = $count->lines()->sole();
    $this->put(route('stock-counts.counts', $count), ['lines' => [$line->id => ['qty' => '2']]]);
    $this->post(route('stock-counts.post', $count));

    $this->post(route('stock-counts.post', $count))->assertSessionHasErrors('count');
    $this->put(route('stock-counts.counts', $count), ['lines' => [$line->id => ['qty' => '9']]])->assertSessionHasErrors('count');
    $this->post(route('stock-counts.cancel', $count))->assertSessionHasErrors('count');
    $this->get(route('stock-counts.review', $count))->assertRedirect(route('stock-counts.show', $count));

    expect($line->fresh()->qty_counted)->toBe('2.000')
        ->and(StockTransaction::query()->where('stock_count_id', $count->id)->count())->toBe(1);
});

test('counted quantities must be zero or positive', function (string $qty) {
    $count = newCount();
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'item', 'item_id' => $this->blade->id]);
    $this->post(route('stock-counts.start', $count));

    $this->put(route('stock-counts.counts', $count), ['lines' => [$count->lines()->value('id') => ['qty' => $qty]]])
        ->assertSessionHasErrors('lines.'.$count->lines()->value('id').'.qty');
})->with(['-1', 'two', '1.2345']);

test('a found item without a cost asks for one on review and posts with it', function () {
    $servo = Item::factory()->create(['sku' => 'SP-10005']);
    $count = newCount();
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'item', 'item_id' => $servo->id]);
    $this->post(route('stock-counts.start', $count));
    $line = $count->lines()->sole();
    $this->put(route('stock-counts.counts', $count), ['lines' => [$line->id => ['qty' => '1']]]);

    $this->get(route('stock-counts.review', $count))->assertSee('No cost at this site yet');
    $this->post(route('stock-counts.post', $count))->assertSessionHasErrors("costs.{$line->id}");
    $this->post(route('stock-counts.post', $count), ['costs' => [$line->id => '238']])->assertSessionHasNoErrors();

    expect(Stock::query()->where('item_id', $servo->id)->value('avg_cost'))->toBe('238.0000');
});

test('a manager cannot count the other site; operators cannot see counts', function () {
    $this->post(route('stock-counts.store'), ['site_id' => $this->georgia->id])->assertForbidden();

    $operator = User::factory()->operator($this->colorado)->create();
    $this->actingAs($operator)->get(route('stock-counts.index'))->assertForbidden();
});

test('the list shows how many items are due and exports CSV; the sheet exports for printing', function () {
    $count = newCount();
    $this->post(route('stock-counts.lines.store', $count), ['by' => 'due']);

    $this->get(route('stock-counts.index'))->assertSee('2 items are due for counting')->assertSee($count->reference);
    expect($this->get(route('stock-counts.index', ['export' => 'csv']))->streamedContent())->toContain($count->reference)
        ->and($this->get(route('stock-counts.show', [$count, 'export' => 'csv']))->streamedContent())
        ->toContain('Reference,Location,SKU,Name,UoM,Counted,Note')->toContain('CO-A1,CS-30001');
});
