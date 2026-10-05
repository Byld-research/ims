<?php

use App\Models\Item;
use App\Models\MachineType;
use App\Models\MachineTypeItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;

/*
| Machine types and parts lists, with the Truss Saw (type C) as the reference case.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->manager = User::factory()->create();
    $this->trussSaw = MachineType::query()->where('code', 'C')->sole();
});

function csvUpload(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('parts.csv', $contents);
}

test('a machine type is one capital letter', function () {
    $this->actingAs($this->manager);

    $this->post(route('machine-types.store'), ['code' => 'r', 'name' => 'Roof Truss JIG'])->assertSessionHasNoErrors();
    expect(MachineType::query()->where('name', 'Roof Truss JIG')->value('code'))->toBe('R');

    $this->post(route('machine-types.store'), ['code' => 'C', 'name' => 'Duplicate'])->assertSessionHasErrors('code');
    $this->post(route('machine-types.store'), ['code' => 'TS', 'name' => 'Two letters'])->assertSessionHasErrors('code');
});

test('the letter of a type with registered machines cannot change', function () {
    $this->actingAs($this->manager)
        ->put(route('machine-types.update', $this->trussSaw), ['code' => 'Z', 'name' => 'Truss Saw', 'last_serial' => 6])
        ->assertSessionHasErrors('code');
});

test('the last serial cannot drop below a registered machine', function () {
    $this->actingAs($this->manager);

    $this->put(route('machine-types.update', $this->trussSaw), ['code' => 'C', 'name' => 'Truss Saw', 'last_serial' => 3])
        ->assertSessionHasErrors('last_serial');

    $this->put(route('machine-types.update', $this->trussSaw), ['code' => 'C', 'name' => 'Truss Saw', 'last_serial' => 8])
        ->assertSessionHasNoErrors();
    expect($this->trussSaw->fresh()->nextSku())->toBe('009C');
});

test('operators cannot see or manage machine types', function () {
    $this->actingAs(User::factory()->operator()->create());

    $this->get(route('machine-types.index'))->assertForbidden();
    $this->get(route('machine-types.show', $this->trussSaw))->assertForbidden();
});

test('parts list lines are added for all revisions or one revision, updated and removed', function () {
    $blade = Item::factory()->create();
    $servo = Item::factory()->create();

    $this->actingAs($this->manager);
    $this->post(route('machine-types.items.store', $this->trussSaw), ['item_id' => $blade->id, 'reference' => 'Main blade', 'qty_per_machine' => '1', 'is_consumable' => 1])
        ->assertSessionHasNoErrors();
    $this->post(route('machine-types.items.store', $this->trussSaw), ['item_id' => $servo->id, 'revision' => '2.0'])
        ->assertSessionHasNoErrors();

    $line = MachineTypeItem::query()->where('item_id', $blade->id)->sole();
    expect($line->revision)->toBeNull()->and($line->is_consumable)->toBeTrue()
        ->and(MachineTypeItem::query()->where('item_id', $servo->id)->value('revision'))->toBe('2.0');

    $this->put(route('machine-type-items.update', $line), ['revision' => '1.0', 'is_consumable' => 0])->assertSessionHasNoErrors();
    expect($line->fresh()->revision)->toBe('1.0');

    $this->delete(route('machine-type-items.destroy', $line));
    expect(MachineTypeItem::query()->whereKey($line->id)->exists())->toBeFalse();
});

test('criterion 32: an item is listed for all revisions or per revision, never both', function () {
    $blade = Item::factory()->create();
    $servo = Item::factory()->create();
    MachineTypeItem::factory()->for($this->trussSaw)->for($blade)->create(['revision' => null]);
    MachineTypeItem::factory()->for($this->trussSaw)->for($servo)->create(['revision' => '2.0']);

    $this->actingAs($this->manager);

    // Specific revision on top of an all-revisions line.
    $this->post(route('machine-types.items.store', $this->trussSaw), ['item_id' => $blade->id, 'revision' => '2.0'])
        ->assertSessionHasErrors(['item_id' => 'This item is already listed for all revisions. Limit that line to a revision first.']);

    // All revisions on top of a specific line.
    $this->post(route('machine-types.items.store', $this->trussSaw), ['item_id' => $servo->id])
        ->assertSessionHasErrors('item_id');

    // The same specific revision twice.
    $this->post(route('machine-types.items.store', $this->trussSaw), ['item_id' => $servo->id, 'revision' => '2.0'])
        ->assertSessionHasErrors('item_id');

    // Another specific revision is fine.
    $this->post(route('machine-types.items.store', $this->trussSaw), ['item_id' => $servo->id, 'revision' => '1.0'])
        ->assertSessionHasNoErrors();
});

test('a parts list revision must look like major.minor', function () {
    $this->actingAs($this->manager)
        ->post(route('machine-types.items.store', $this->trussSaw), ['item_id' => Item::factory()->create()->id, 'revision' => 'rev2'])
        ->assertSessionHasErrors('revision');
});

test('the Truss Saw page shows the parts list with revisions and the registered saws', function () {
    $servo = Item::factory()->create(['sku' => 'SP-SERVO-20']);
    MachineTypeItem::factory()->for($this->trussSaw)->for($servo)->create(['revision' => '2.0']);

    $this->actingAs($this->manager)
        ->get(route('machine-types.show', $this->trussSaw))
        ->assertOk()
        ->assertSee('C · Truss Saw')
        ->assertSee('next machine: 007C')
        ->assertSee('SP-SERVO-20')
        ->assertSeeInOrder(['003C', 'Truss Saw 1.0', 'BPC002'])
        ->assertSeeInOrder(['004C', 'Truss Saw 2.0', 'BPC001']);
});

test('a Truss Saw parts list is imported from CSV with revisions, and re-importing updates', function () {
    Item::factory()->create(['sku' => 'SP-1']);
    Item::factory()->create(['sku' => 'SP-2']);
    Item::factory()->create(['sku' => 'SP-3']);

    $this->actingAs($this->manager)
        ->post(route('machine-types.import', $this->trussSaw), ['file' => csvUpload(
            "\xEF\xBB\xBFsku,revision,reference,qty_per_machine,is_consumable,note\n".
            "SP-1,,Main blade,1,yes,\n".
            "SP-2,1.0,Axis 1,1,no,stepper\n".
            "SP-2,2.0,Axis 1,2,no,servo\n".
            "SP-3,2.0,,,,\n"
        )])
        ->assertSessionHas('success', 'Parts list imported: 4 added, 0 updated.');

    $this->post(route('machine-types.import', $this->trussSaw), ['file' => csvUpload("SKU,revision,qty_per_machine\nSP-2,2.0,4\n")])
        ->assertSessionHas('success', 'Parts list imported: 0 added, 1 updated.');

    $line = $this->trussSaw->partsList()->where('revision', '2.0')->whereRelation('item', 'sku', 'SP-2')->sole();
    expect($line->qty_per_machine)->toBe('4.000');
});

test('an import with any bad line imports nothing and lists every problem', function () {
    Item::factory()->create(['sku' => 'SP-1']);
    Item::factory()->create(['sku' => 'SP-2']);
    Item::factory()->inactive()->create(['sku' => 'SP-OLD']);

    $this->actingAs($this->manager)
        ->post(route('machine-types.import', $this->trussSaw), ['file' => csvUpload(
            "sku,revision,qty_per_machine\n".
            "SP-1,,2\n".      // line 2: fine on its own
            "NOPE-9,,1\n".    // line 3: unknown
            "SP-OLD,,1\n".    // line 4: inactive
            "SP-1,,3\n".      // line 5: duplicate of line 2
            "SP-2,v2,1\n"     // line 6: bad revision
        )])
        ->assertSessionHas('importErrors', fn (array $errors) => count($errors) === 4
            && str_contains($errors[0], 'NOPE-9')
            && str_contains($errors[1], 'inactive')
            && str_contains($errors[2], 'already appears on line 2')
            && str_contains($errors[3], 'v2'));

    expect($this->trussSaw->partsList()->count())->toBe(0);
});

test('an import may not mix all-revision and revision lines for one item', function () {
    $item = Item::factory()->create(['sku' => 'SP-1']);
    MachineTypeItem::factory()->for($this->trussSaw)->for($item)->create(['revision' => null]);

    $this->actingAs($this->manager)
        ->post(route('machine-types.import', $this->trussSaw), ['file' => csvUpload("sku,revision\nSP-1,2.0\n")])
        ->assertSessionHas('importErrors', fn (array $errors) => str_contains($errors[0], 'both for all revisions and for specific revisions'));

    $this->post(route('machine-types.import', $this->trussSaw), ['file' => csvUpload("sku,revision\nSP-1,\n")])
        ->assertSessionHas('success', 'Parts list imported: 0 added, 1 updated.');
});

test('an import without a sku column is rejected', function () {
    $this->actingAs($this->manager)
        ->post(route('machine-types.import', $this->trussSaw), ['file' => csvUpload("part,qty\nSP-1,2\n")])
        ->assertSessionHas('importErrors');
});

test('the exported Truss Saw parts list can be imported into another type', function () {
    MachineTypeItem::factory()->for($this->trussSaw)->create(['revision' => null, 'is_consumable' => true]);
    MachineTypeItem::factory()->for($this->trussSaw)->create(['revision' => '2.0']);

    $csv = $this->actingAs($this->manager)->get(route('machine-types.show', [$this->trussSaw, 'export' => 'csv']))->streamedContent();

    $trussJig = MachineType::query()->where('code', 'T')->sole();
    $this->post(route('machine-types.import', $trussJig), ['file' => csvUpload($csv)])
        ->assertSessionHas('success', 'Parts list imported: 2 added, 0 updated.');

    expect($trussJig->partsList()->whereNull('revision')->where('is_consumable', true)->count())->toBe(1)
        ->and($trussJig->partsList()->where('revision', '2.0')->count())->toBe(1);
});
