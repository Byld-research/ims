<?php

use App\Models\Item;
use App\Models\MachineType;
use App\Models\MachineTypeItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->manager = User::factory()->create();
});

function csvUpload(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('parts.csv', $contents);
}

test('a manager creates a machine type with an upper-cased code', function () {
    $this->actingAs($this->manager)
        ->post(route('machine-types.store'), ['code' => 'truss_saw', 'name' => 'Truss Saw'])
        ->assertSessionHasNoErrors();

    expect(MachineType::query()->sole()->code)->toBe('TRUSS_SAW');
});

test('operators cannot see or manage machine types', function () {
    $type = MachineType::factory()->create();
    $this->actingAs(User::factory()->operator()->create());

    $this->get(route('machine-types.index'))->assertForbidden();
    $this->get(route('machine-types.show', $type))->assertForbidden();
});

test('parts list lines are added, updated and removed', function () {
    $type = MachineType::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('machine-types.items.store', $type), ['item_id' => $item->id, 'reference' => 'POS-4', 'qty_per_machine' => '2', 'is_consumable' => 1])
        ->assertSessionHasNoErrors();

    $line = MachineTypeItem::query()->sole();
    expect($line->is_consumable)->toBeTrue();

    $this->post(route('machine-types.items.store', $type), ['item_id' => $item->id])->assertSessionHasErrors('item_id');

    $this->put(route('machine-type-items.update', $line), ['reference' => 'POS-5', 'is_consumable' => 0])->assertSessionHasNoErrors();
    expect($line->fresh()->reference)->toBe('POS-5')->and($line->fresh()->is_consumable)->toBeFalse();

    $this->delete(route('machine-type-items.destroy', $line));
    expect(MachineTypeItem::query()->count())->toBe(0);
});

test('a CSV parts list is imported and re-importing updates by SKU', function () {
    $type = MachineType::factory()->create();
    Item::factory()->create(['sku' => 'SP-1']);
    Item::factory()->create(['sku' => 'SP-2']);

    $this->actingAs($this->manager)
        ->post(route('machine-types.import', $type), ['file' => csvUpload("\xEF\xBB\xBFsku,reference,qty_per_machine,is_consumable,note\nSP-1,A1,2,yes,\nSP-2,,,no,check yearly\n")])
        ->assertSessionHas('success', 'Parts list imported: 2 added, 0 updated.');

    expect($type->partsList()->count())->toBe(2);

    $this->post(route('machine-types.import', $type), ['file' => csvUpload("SKU,qty_per_machine\nSP-1,4\n")])
        ->assertSessionHas('success', 'Parts list imported: 0 added, 1 updated.');

    $line = $type->partsList()->whereRelation('item', 'sku', 'SP-1')->sole();
    expect($line->qty_per_machine)->toBe('4.000');
});

test('an import with any bad line imports nothing and lists every problem', function () {
    $type = MachineType::factory()->create();
    Item::factory()->create(['sku' => 'SP-1']);
    Item::factory()->inactive()->create(['sku' => 'SP-OLD']);

    $this->actingAs($this->manager)
        ->post(route('machine-types.import', $type), ['file' => csvUpload("sku,qty_per_machine\nSP-1,2\nNOPE-9,1\nSP-OLD,1\nSP-1,3\nSP-1X,-2\n")])
        ->assertSessionHas('importErrors', fn (array $errors) => count($errors) === 4
            && str_contains($errors[0], 'NOPE-9')
            && str_contains($errors[1], 'inactive')
            && str_contains($errors[2], 'already appears on line 2'));

    expect($type->partsList()->count())->toBe(0);
});

test('an import without a sku column is rejected', function () {
    $type = MachineType::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('machine-types.import', $type), ['file' => csvUpload("part,qty\nSP-1,2\n")])
        ->assertSessionHas('importErrors');
});

test('the exported parts list can be imported again', function () {
    $type = MachineType::factory()->create();
    MachineTypeItem::factory()->count(2)->for($type)->create(['is_consumable' => true]);

    $csv = $this->actingAs($this->manager)->get(route('machine-types.show', [$type, 'export' => 'csv']))->streamedContent();

    $other = MachineType::factory()->create();
    $this->post(route('machine-types.import', $other), ['file' => csvUpload($csv)])
        ->assertSessionHas('success', 'Parts list imported: 2 added, 0 updated.');

    expect($other->partsList()->where('is_consumable', true)->count())->toBe(2);
});
