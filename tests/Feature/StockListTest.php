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
