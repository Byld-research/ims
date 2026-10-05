<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    $this->manager = User::factory()->create();
});

test('a manager creates a subcategory under a top-level group', function () {
    $parent = Category::factory()->structural()->create(['name' => 'Spare Parts']);

    $this->actingAs($this->manager)
        ->post(route('categories.store'), ['name' => 'Bearings', 'parent_id' => $parent->id, 'default_bin' => 'A-01'])
        ->assertRedirect(route('categories.index'));

    $category = Category::query()->where('name', 'Bearings')->sole();
    expect($category->parent_id)->toBe($parent->id)
        ->and($category->is_structural)->toBeFalse()
        ->and($category->default_bin)->toBe('A-01');
});

test('categories nest only one level deep', function () {
    $child = Category::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('categories.store'), ['name' => 'Too deep', 'parent_id' => $child->id])
        ->assertSessionHasErrors('parent_id');
});

test('a category with subcategories cannot be moved under another', function () {
    $child = Category::factory()->create();
    $otherTop = Category::factory()->structural()->create();

    $this->actingAs($this->manager)
        ->put(route('categories.update', $child->parent), ['name' => $child->parent->name, 'parent_id' => $otherTop->id])
        ->assertSessionHasErrors('parent_id');
});

test('a category with items cannot become structural', function () {
    $item = Item::factory()->create();

    $this->actingAs($this->manager)
        ->put(route('categories.update', $item->category), ['name' => $item->category->name, 'parent_id' => $item->category->parent_id, 'is_structural' => 1])
        ->assertSessionHasErrors('is_structural');
});

test('names are unique within the same parent', function () {
    $existing = Category::factory()->create(['name' => 'Bearings']);

    $this->actingAs($this->manager)
        ->post(route('categories.store'), ['name' => 'Bearings', 'parent_id' => $existing->parent_id])
        ->assertSessionHasErrors('name');
});

test('the category list renders the tree', function () {
    $child = Category::factory()->create(['name' => 'Hydraulic']);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('categories.index'))
        ->assertOk()
        ->assertSeeInOrder([$child->parent->name, 'Hydraulic'])
        ->assertDontSee(__('New category'));
});
