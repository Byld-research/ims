<?php

use App\Models\Item;
use App\Models\Machine;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->manager = User::factory()->manager($this->colorado)->create();
});

test('the kanban view lists refills first, then by bins left, and exports CSV', function () {
    $found = ReasonCode::adjustment('FOUND');
    foreach (['KB-PLENTY' => ['60', '10'], 'KB-REFILL' => ['5', '6'], 'KB-TWO' => ['12', '6']] as $sku => [$qty, $bin]) {
        $item = Item::factory()->create(['sku' => $sku]);
        app(StockService::class)->adjust($item, $this->colorado, true, $qty, $found, $this->manager, '1');
        Stock::query()->where('item_id', $item->id)->update(['is_kanban' => true, 'bin_qty' => $bin]);
    }
    Item::factory()->create(['sku' => 'NOT-KANBAN']);

    $this->actingAs(User::factory()->operator($this->colorado)->create())
        ->get(route('kanban.index'))
        ->assertOk()
        ->assertSeeInOrder(['KB-REFILL', 'KB-TWO', 'KB-PLENTY'])
        ->assertSee('Refill')
        ->assertDontSee('NOT-KANBAN');

    expect($this->get(route('kanban.index', ['export' => 'csv']))->streamedContent())
        ->toContain('Site,SKU,Name,UoM,Bin,"Qty per bin","In stock","Bins left","Needs refill","On order"')
        ->toContain('KB-REFILL');
});

test('the menu groups links into sections and shows the Issue button to managers', function () {
    $this->actingAs($this->manager)->get(route('stock.index'))
        ->assertOk()
        ->assertSeeInOrder(['Dashboard', 'Stock', 'Purchasing', 'Machines'])
        ->assertSee('Min levels &amp; bins', false)
        ->assertSee('Machine types')
        ->assertSee('Suppliers')
        ->assertSee('Transfer in')
        ->assertSee(route('issues.create'));
});

test('operators see only what they may open and no Issue button', function () {
    $this->actingAs(User::factory()->operator($this->colorado)->create())->get(route('stock.index'))
        ->assertOk()
        ->assertSee('Kanban')
        ->assertDontSee('Min levels &amp; bins', false)
        ->assertDontSee('Machine types')
        ->assertDontSee('Suppliers')
        ->assertDontSee(route('issues.create'))
        ->assertDontSee(route('stock-counts.index'));
});

test('a section is marked current on any of its pages', function () {
    $item = Item::factory()->create();

    $response = $this->actingAs($this->manager)->get(route('items.show', $item))->assertOk();

    expect(substr_count($response->getContent(), 'aria-current="page"'))->toBeGreaterThanOrEqual(1)
        ->and($response->getContent())->toMatch('/aria-current="page"\s*>\s*<span[^>]*>Stock list/');
});

test('category, item history and machine consumption lists export CSV', function () {
    $item = Item::factory()->create(['sku' => 'SP-10001']);
    app(StockService::class)->adjust($item, $this->colorado, true, '3', ReasonCode::adjustment('OPENING'), $this->manager, '412', 'opening');
    app(StockService::class)->issueToMachine($item, Machine::query()->where('sku', '004C')->sole(), '1', $this->manager, 'blade change');

    $this->actingAs($this->manager);

    expect($this->get(route('categories.index', ['export' => 'csv']))->streamedContent())->toContain('Group,Category,Structural')
        ->and($this->get(route('items.show', [$item, 'export' => 'csv']))->streamedContent())
        ->toContain('ADJUSTMENT')->toContain('ISSUE_MACHINE')->toContain('004C')->toContain('OPENING')
        ->and($this->get(route('machines.show', [Machine::query()->where('sku', '004C')->sole(), 'export' => 'csv']))->streamedContent())
        ->toContain('blade change')->not->toContain('OPENING');
});
