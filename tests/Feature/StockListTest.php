<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->georgia = Site::factory()->create(['code' => 'BPC001']);
    $this->colorado = Site::factory()->create(['code' => 'BPC002']);
    $this->user = User::factory()->operator($this->colorado)->create();
});

test('lists active items with their quantity at every site', function () {
    $item = Item::factory()->create(['sku' => 'SP-1']);
    DB::table('stocks')->insert([
        ['item_id' => $item->id, 'site_id' => $this->colorado->id, 'qty' => 12],
        ['item_id' => $item->id, 'site_id' => $this->georgia->id, 'qty' => 3.5],
    ]);
    Item::factory()->inactive()->create(['sku' => 'OLD-1']);

    $this->actingAs($this->user)->get(route('stock.index'))
        ->assertOk()
        ->assertSee('SP-1')
        ->assertSeeInOrder(['BPC001', 'BPC002'])
        ->assertSeeInOrder(['12', '3.5'])
        ->assertDontSee('OLD-1');
});

test('searches by SKU and name', function () {
    Item::factory()->create(['sku' => 'SP-100', 'name' => 'Saw blade']);
    Item::factory()->create(['sku' => 'SP-200', 'name' => 'Hydraulic hose']);

    $this->actingAs($this->user);
    $this->get(route('stock.index', ['q' => 'blade']))->assertSee('SP-100')->assertDontSee('SP-200');
    $this->get(route('stock.index', ['q' => 'SP-2']))->assertSee('SP-200')->assertDontSee('SP-100');
});

test('a top-level category filter includes its subcategories', function () {
    $top = Category::factory()->structural()->create();
    $inside = Item::factory()->for(Category::factory()->state(['parent_id' => $top->id]))->create();
    $outside = Item::factory()->create();

    $this->actingAs($this->user)->get(route('stock.index', ['category' => $top->id]))
        ->assertSee($inside->sku)
        ->assertDontSee($outside->sku);
});

test('filters by criticality and can include inactive items', function () {
    Item::factory()->create(['sku' => 'CRIT-A', 'criticality' => 'A']);
    Item::factory()->create(['sku' => 'CRIT-C', 'criticality' => 'C']);
    Item::factory()->inactive()->create(['sku' => 'GONE-1', 'criticality' => 'A']);

    $this->actingAs($this->user);
    $this->get(route('stock.index', ['criticality' => 'A']))->assertSee('CRIT-A')->assertDontSee('CRIT-C')->assertDontSee('GONE-1');
    $this->get(route('stock.index', ['criticality' => 'A', 'inactive' => 1]))->assertSee('GONE-1');
});

test('exports the filtered list as CSV', function () {
    Item::factory()->create(['sku' => 'SP-1', 'name' => '=HYPERLINK("x")']);
    Item::factory()->create(['sku' => 'SP-2', 'criticality' => 'C']);

    $response = $this->actingAs($this->user)->get(route('stock.index', ['q' => 'SP-1', 'export' => 'csv']));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toContain('SKU,Name,Category')
        ->toContain('"BPC001 qty","BPC002 qty"')
        ->toContain('SP-1')
        ->toContain("'=HYPERLINK") // formula injection neutralised
        ->not->toContain('SP-2');
});

test('replenishment filter follows SPEC 5.5 at the selected site, class A first', function () {
    $low = Item::factory()->create(['sku' => 'LOW-C', 'criticality' => 'C']);
    $lowA = Item::factory()->create(['sku' => 'LOW-A', 'criticality' => 'A']);
    $ok = Item::factory()->create(['sku' => 'OK-1']);
    $noMin = Item::factory()->create(['sku' => 'NOMIN-1']);
    $kanbanAtBin = Item::factory()->create(['sku' => 'KB-AT']);
    $kanbanAbove = Item::factory()->create(['sku' => 'KB-ABOVE']);
    $lowElsewhere = Item::factory()->create(['sku' => 'LOW-OTHER-SITE']);

    $here = $this->user->site_id;
    DB::table('stocks')->insert([
        ['item_id' => $low->id, 'site_id' => $here, 'qty' => 3, 'min_level' => 5, 'is_kanban' => false, 'bin_qty' => null],
        ['item_id' => $lowA->id, 'site_id' => $here, 'qty' => 0, 'min_level' => 1, 'is_kanban' => false, 'bin_qty' => null],
        ['item_id' => $ok->id, 'site_id' => $here, 'qty' => 5, 'min_level' => 5, 'is_kanban' => false, 'bin_qty' => null],
        ['item_id' => $noMin->id, 'site_id' => $here, 'qty' => 3, 'min_level' => 0, 'is_kanban' => false, 'bin_qty' => null],
        // Criterion 20: bin 20, stock 20 needs a refill; stock 21 does not.
        ['item_id' => $kanbanAtBin->id, 'site_id' => $here, 'qty' => 20, 'min_level' => 100, 'is_kanban' => true, 'bin_qty' => 20],
        ['item_id' => $kanbanAbove->id, 'site_id' => $here, 'qty' => 21, 'min_level' => 100, 'is_kanban' => true, 'bin_qty' => 20],
        ['item_id' => $lowElsewhere->id, 'site_id' => $this->georgia->id, 'qty' => 0, 'min_level' => 2, 'is_kanban' => false, 'bin_qty' => null],
    ]);

    $this->actingAs($this->user)->get(route('stock.index', ['below' => 1]))
        ->assertOk()
        ->assertSeeInOrder(['LOW-A', 'LOW-C'])
        ->assertSee('KB-AT')
        ->assertSee('Refill')
        ->assertSee('Out')
        ->assertDontSee('OK-1')
        ->assertDontSee('NOMIN-1')
        ->assertDontSee('KB-ABOVE')
        ->assertDontSee('LOW-OTHER-SITE');
});

test('the CSV export carries levels and value at the selected site', function () {
    $item = Item::factory()->create(['sku' => 'SP-9']);
    DB::table('stocks')->insert(['item_id' => $item->id, 'site_id' => $this->user->site_id, 'qty' => 4, 'avg_cost' => '2.5', 'min_level' => 5, 'bin' => 'GA-1']);

    $csv = $this->actingAs($this->user)->get(route('stock.index', ['export' => 'csv']))->streamedContent();

    expect($csv)->toContain('"BPC002 min level","BPC002 location"')
        ->toContain('SP-9')
        ->toContain('5.000,GA-1,no,,2.5000,10.0000,yes');
});
