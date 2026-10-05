<?php

use App\Models\Item;
use App\Models\User;

test('finds active items by SKU, name or MPN, exact SKU first', function () {
    Item::factory()->create(['sku' => 'SP-100', 'name' => 'Blade holder']);
    Item::factory()->create(['sku' => 'SP-1', 'name' => 'Saw blade']);
    Item::factory()->create(['sku' => 'XX-9', 'name' => 'Hose', 'mpn' => 'SP-1-MPN']);
    Item::factory()->inactive()->create(['sku' => 'SP-1-OLD']);

    $results = $this->actingAs(User::factory()->operator()->create())
        ->getJson(route('items.search', ['q' => 'SP-1']))
        ->assertOk()
        ->json();

    expect(array_column($results, 'sku'))->toBe(['SP-1', 'SP-100', 'XX-9'])
        ->and($results[0])->toHaveKeys(['id', 'sku', 'name', 'uom']);
});

test('needs at least two characters', function () {
    Item::factory()->create(['sku' => 'A1']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('items.search', ['q' => 'A']))
        ->assertExactJson([]);
});

test('treats wildcard characters literally', function () {
    Item::factory()->create(['sku' => 'AB-1']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('items.search', ['q' => '%%']))
        ->assertExactJson([]);
});
