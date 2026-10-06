<?php

use App\Models\Item;
use App\Models\Site;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->manager = User::factory()->manager($this->colorado)->create();
    $this->blade = Item::factory()->create(['sku' => 'SP-BLADE']);
    $this->filter = Item::factory()->create(['sku' => 'CS-FILTER']);
});

function levels(array $rows, ?Site $site = null): array
{
    return ['site_id' => ($site ?? test()->colorado)->id, 'rows' => $rows];
}

test('a manager sets min levels, bins and kanban at their own site', function () {
    $this->actingAs($this->manager)
        ->put(route('stock.levels.update'), levels([
            $this->blade->id => ['min_level' => '2', 'bin' => 'CO-A1', 'is_kanban' => '0', 'bin_qty' => ''],
            $this->filter->id => ['min_level' => '', 'bin' => 'CO-K4', 'is_kanban' => '1', 'bin_qty' => '6'],
        ]))
        ->assertSessionHas('success', '2 items updated at BPC002.');

    expect(Stock::query()->where('item_id', $this->blade->id)->sole())
        ->min_level->toBe('2.000')->bin->toBe('CO-A1')->is_kanban->toBeFalse()->qty->toBe('0.000')
        ->and(Stock::query()->where('item_id', $this->filter->id)->sole())
        ->is_kanban->toBeTrue()->bin_qty->toBe('6.000');
});

test('untouched rows for items never stocked here create nothing', function () {
    $this->actingAs($this->manager)
        ->put(route('stock.levels.update'), levels([
            $this->blade->id => ['min_level' => '', 'bin' => '', 'is_kanban' => '0', 'bin_qty' => ''],
        ]))
        ->assertSessionHas('success', 'Nothing changed.');

    expect(Stock::query()->count())->toBe(0);
});

test('kanban needs a bin quantity greater than zero', function (string $binQty) {
    $this->actingAs($this->manager)
        ->put(route('stock.levels.update'), levels([
            $this->filter->id => ['is_kanban' => '1', 'bin_qty' => $binQty],
        ]))
        ->assertSessionHasErrors("rows.{$this->filter->id}.bin_qty");
})->with(['', '0']);

test('a negative min level is refused and nothing is saved', function () {
    $this->actingAs($this->manager)
        ->put(route('stock.levels.update'), levels([
            $this->blade->id => ['min_level' => '3'],
            $this->filter->id => ['min_level' => '-1'],
        ]))
        ->assertSessionHasErrors("rows.{$this->filter->id}.min_level");

    expect(Stock::query()->count())->toBe(0);
});

test('levels are per site: a manager cannot set them at the other site', function () {
    $this->actingAs($this->manager)
        ->put(route('stock.levels.update'), levels([$this->blade->id => ['min_level' => '2']], $this->georgia))
        ->assertForbidden();

    $this->get(route('stock.levels', ['site' => $this->georgia->id]))->assertForbidden();
});

test('the editor lists items with their current settings at the site', function () {
    Stock::query()->create(['item_id' => $this->blade->id, 'site_id' => $this->colorado->id, 'min_level' => 2, 'bin' => 'CO-A1']);
    Stock::query()->create(['item_id' => $this->blade->id, 'site_id' => $this->georgia->id, 'bin' => 'GA-Z9']);

    $this->actingAs($this->manager)
        ->get(route('stock.levels'))
        ->assertOk()
        ->assertSee('Min levels &amp; locations · BPC002', false)
        ->assertSee('CO-A1')
        ->assertDontSee('GA-Z9');
});

test('an administrator in the consolidated view is asked to choose a site', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('site.select'), ['site' => 'all']);

    $this->get(route('stock.levels'))->assertOk()->assertSee('Levels are set per site');
});
