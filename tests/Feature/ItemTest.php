<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\MachineTypeItem;
use App\Models\Site;
use App\Models\SupplierItem;
use App\Models\User;

beforeEach(function () {
    $this->site = Site::factory()->create();
    $this->manager = User::factory()->manager($this->site)->create();
    $this->category = Category::factory()->create();
});

function itemPayload(array $overrides = []): array
{
    return [
        'sku' => 'SP-00001',
        'name' => 'Linear bearing',
        'category_id' => test()->category->id,
        'uom' => 'pc',
        'criticality' => 'A',
        ...$overrides,
    ];
}

test('a manager creates an item', function () {
    $response = $this->actingAs($this->manager)->post(route('items.store'), itemPayload());

    $item = Item::query()->where('sku', 'SP-00001')->sole();
    $response->assertRedirect(route('items.show', $item));
    expect($item->criticality->value)->toBe('A')->and($item->is_active)->toBeTrue();
});

test('an operator cannot create items', function () {
    $this->actingAs(User::factory()->operator($this->site)->create())
        ->post(route('items.store'), itemPayload())
        ->assertForbidden();

    $this->get(route('items.create'))->assertForbidden();
});

test('items cannot be put in a structural category', function () {
    $this->actingAs($this->manager)
        ->post(route('items.store'), itemPayload(['category_id' => $this->category->parent_id]))
        ->assertSessionHasErrors('category_id');
});

test('the SKU is required, unique and at most 40 characters', function () {
    Item::factory()->create(['sku' => 'SP-00001']);

    $this->actingAs($this->manager);
    $this->post(route('items.store'), itemPayload())->assertSessionHasErrors('sku');
    $this->post(route('items.store'), itemPayload(['sku' => '']))->assertSessionHasErrors('sku');
    $this->post(route('items.store'), itemPayload(['sku' => str_repeat('X', 41)]))->assertSessionHasErrors('sku');
});

test('the SKU pattern is enforced once configured', function () {
    config(['ims.sku_pattern' => '/^SP-\d{5}$/', 'ims.sku_pattern_hint' => 'Use SP- followed by five digits.']);

    $this->actingAs($this->manager)
        ->post(route('items.store'), itemPayload(['sku' => 'bearing-1']))
        ->assertSessionHasErrors(['sku' => 'Use SP- followed by five digits.']);

    $this->post(route('items.store'), itemPayload(['sku' => 'SP-12345']))->assertSessionHasNoErrors();
});

test('the SKU can change until the item has stock movements', function () {
    $item = Item::factory()->create(['sku' => 'OLD-1', 'category_id' => $this->category->id]);

    $this->actingAs($this->manager)
        ->put(route('items.update', $item), itemPayload(['sku' => 'NEW-1']))
        ->assertSessionHasNoErrors();
    expect($item->fresh()->sku)->toBe('NEW-1');

    ledgerRow($item, $this->site);

    $this->put(route('items.update', $item), itemPayload(['sku' => 'NEWER-1']))->assertSessionHasErrors('sku');
    expect($item->fresh()->sku)->toBe('NEW-1');

    // Other fields stay editable.
    $this->put(route('items.update', $item), itemPayload(['sku' => 'NEW-1', 'name' => 'Renamed']))->assertSessionHasNoErrors();
    expect($item->fresh()->name)->toBe('Renamed');
});

test('items are deactivated, never deleted', function () {
    $item = Item::factory()->create(['category_id' => $this->category->id]);

    $this->actingAs($this->manager)
        ->put(route('items.update', $item), itemPayload(['sku' => $item->sku, 'is_active' => 0]))
        ->assertSessionHasNoErrors();

    expect($item->fresh()->is_active)->toBeFalse();
    $this->delete('/items/'.$item->id)->assertMethodNotAllowed();
});

test('the item page shows stock per site, suppliers and machine types to everyone', function () {
    $item = Item::factory()->create();
    $other = Site::factory()->create(['code' => 'BPC777']);
    $link = SupplierItem::factory()->for($item)->create(['supplier_sku' => 'VEND-77']);
    $machineType = MachineTypeItem::factory()->for($item)->create()->machineType;

    $this->actingAs(User::factory()->operator($this->site)->create())
        ->get(route('items.show', $item))
        ->assertOk()
        ->assertSee($item->sku)
        ->assertSee($this->site->code)
        ->assertSee('BPC777')
        ->assertSee($link->supplier->name)
        ->assertSee('VEND-77')
        ->assertSee($machineType->name)
        ->assertDontSee(route('items.edit', $item));
});
