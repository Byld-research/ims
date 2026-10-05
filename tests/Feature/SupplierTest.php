<?php

use App\Models\Item;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;

beforeEach(function () {
    $this->manager = User::factory()->create();
});

test('a manager creates and edits a supplier', function () {
    $this->actingAs($this->manager)
        ->post(route('suppliers.store'), ['name' => 'Kraków warehouse', 'lead_time_days' => 21, 'contact_email' => 'krk@example.com'])
        ->assertSessionHasNoErrors();

    $supplier = Supplier::query()->where('name', 'Kraków warehouse')->sole();

    $this->put(route('suppliers.update', $supplier), ['name' => 'Kraków warehouse', 'lead_time_days' => '', 'is_active' => 0])
        ->assertRedirect(route('suppliers.show', $supplier));

    expect($supplier->fresh()->is_active)->toBeFalse()
        ->and($supplier->fresh()->lead_time_days)->toBeNull();
});

test('operators cannot see or edit suppliers', function () {
    $supplier = Supplier::factory()->create();
    $this->actingAs(User::factory()->operator()->create());

    $this->get(route('suppliers.index'))->assertForbidden();
    $this->get(route('suppliers.show', $supplier))->assertForbidden();
    $this->post(route('suppliers.store'), ['name' => 'X'])->assertForbidden();
});

test('the list hides inactive suppliers unless asked and exports CSV', function () {
    Supplier::factory()->create(['name' => 'Grainger']);
    Supplier::factory()->create(['name' => 'Old Vendor', 'is_active' => false]);

    $this->actingAs($this->manager);
    $this->get(route('suppliers.index'))->assertSee('Grainger')->assertDontSee('Old Vendor');
    $this->get(route('suppliers.index', ['inactive' => 1]))->assertSee('Old Vendor');

    expect($this->get(route('suppliers.index', ['export' => 'csv']))->streamedContent())
        ->toContain('Grainger')->not->toContain('Old Vendor');
});

test('a manager links an item with price and pack size', function () {
    $supplier = Supplier::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('suppliers.items.store', $supplier), [
            'item_id' => $item->id, 'supplier_sku' => 'G-123', 'last_price' => '12.3456', 'pack_size' => '10',
        ])
        ->assertSessionHasNoErrors();

    $link = SupplierItem::query()->sole();
    expect($link->last_price)->toBe('12.3456')
        ->and($link->pack_size)->toBe('10.000');

    $this->get(route('suppliers.show', $supplier))->assertSee('G-123')->assertSee('12.35');
});

test('an item is linked to the same supplier only once', function () {
    $link = SupplierItem::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('suppliers.items.store', $link->supplier), ['item_id' => $link->item_id])
        ->assertSessionHasErrors('item_id');
});

test('prices are validated as non-negative decimals with at most four places', function (string $price) {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('suppliers.items.store', $supplier), ['item_id' => Item::factory()->create()->id, 'last_price' => $price])
        ->assertSessionHasErrors('last_price');
})->with(['-1', '1.23456', 'abc']);

test('a link can be updated and removed', function () {
    $link = SupplierItem::factory()->create(['last_price' => 5]);

    $this->actingAs($this->manager)
        ->put(route('supplier-items.update', $link), ['last_price' => '6.5', 'pack_size' => '2'])
        ->assertSessionHasNoErrors();
    expect($link->fresh()->last_price)->toBe('6.5000');

    $this->delete(route('supplier-items.destroy', $link))->assertSessionHasNoErrors();
    expect(SupplierItem::query()->count())->toBe(0);
});
