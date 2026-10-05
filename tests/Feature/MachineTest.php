<?php

use App\Models\Item;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\MachineTypeItem;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
| The machine register, with type C (Truss Saw) as the reference case (SPEC 9, 13.29–33):
| 003C rev 1.0 at BPC002 Georgia, 004C rev 2.0 at BPC001 Colorado; 005C and 006C are in Poland.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->colorado = Site::query()->where('code', 'BPC001')->sole();
    $this->georgia = Site::query()->where('code', 'BPC002')->sole();
    $this->trussSaw = MachineType::query()->where('code', 'C')->sole();
    $this->saw003 = Machine::query()->where('sku', '003C')->sole();
    $this->saw004 = Machine::query()->where('sku', '004C')->sole();
    $this->admin = User::query()->where('role', 'ADMIN')->sole();
});

function truss(array $overrides = []): array
{
    return [
        'machine_type_id' => test()->trussSaw->id,
        'sku' => '007C',
        'name' => 'Truss Saw',
        'revision' => '2.0',
        'site_id' => test()->colorado->id,
        ...$overrides,
    ];
}

test('criterion 29: the next Truss Saw is proposed as 007C, skipping the saws in Poland', function () {
    expect($this->trussSaw->nextSku())->toBe('007C');

    $this->actingAs($this->admin)
        ->get(route('machines.create', ['type' => $this->trussSaw->id]))
        ->assertOk()
        ->assertSee('007C');
});

test('registering a machine raises the type counter', function () {
    $this->actingAs($this->admin)->post(route('machines.store'), truss(['sku' => '010C']))->assertSessionHasNoErrors();

    expect($this->trussSaw->fresh()->last_serial)->toBe(10)
        ->and($this->trussSaw->fresh()->nextSku())->toBe('011C');
});

test('an older serial can be registered without lowering the counter', function () {
    $this->actingAs($this->admin)->post(route('machines.store'), truss(['sku' => '005C']))->assertSessionHasNoErrors();

    expect($this->trussSaw->fresh()->last_serial)->toBe(6);
});

test('criterion 30: the SKU letter must match the type, and SKUs are unique', function () {
    $this->actingAs($this->admin);

    $this->post(route('machines.store'), truss(['sku' => '007A']))->assertSessionHasErrors(['sku' => 'A Truss Saw machine’s SKU must end in C, e.g. 007C.']);
    $this->post(route('machines.store'), truss(['sku' => '004C']))->assertSessionHasErrors('sku');
    $this->post(route('machines.store'), truss(['sku' => '7C']))->assertSessionHasErrors('sku');
    $this->post(route('machines.store'), truss(['sku' => '007c']))->assertSessionHasNoErrors(); // upper-cased
});

test('the revision must look like major.minor and the name defaults to the type name', function () {
    $this->actingAs($this->admin);

    $this->post(route('machines.store'), truss(['revision' => 'v2']))->assertSessionHasErrors('revision');
    $this->post(route('machines.store'), truss(['name' => '']))->assertSessionHasNoErrors();

    expect(Machine::query()->where('sku', '007C')->value('name'))->toBe('Truss Saw');
});

test('only administrators register and edit machines', function () {
    $manager = User::factory()->manager($this->colorado)->create();

    $this->actingAs($manager)->post(route('machines.store'), truss())->assertForbidden();
    $this->actingAs($manager)->get(route('machines.edit', $this->saw004))->assertForbidden();
});

test('the register lists the machines at the selected site', function () {
    $this->actingAs(User::factory()->operator($this->georgia)->create())
        ->get(route('machines.index'))
        ->assertOk()
        ->assertSee('003C')
        ->assertSee('004A')
        ->assertDontSee('004C')
        ->assertDontSee('008W');

    $this->get(route('machines.index', ['type' => $this->trussSaw->id]))
        ->assertSee('003C')
        ->assertDontSee('004A');
});

test('criterion 31: lines without a revision apply to both saws, revision lines only to their revision', function () {
    $blade = Item::factory()->create(['sku' => 'SP-BLADE']);
    $stepper = Item::factory()->create(['sku' => 'SP-STEP-10']);
    $servo = Item::factory()->create(['sku' => 'SP-SERVO-20']);

    MachineTypeItem::factory()->for($this->trussSaw)->for($blade)->create(['revision' => null]);
    MachineTypeItem::factory()->for($this->trussSaw)->for($stepper)->create(['revision' => '1.0']);
    MachineTypeItem::factory()->for($this->trussSaw)->for($servo)->create(['revision' => '2.0']);

    expect($this->saw003->partsList()->pluck('item.sku')->all())->toBe(['SP-BLADE', 'SP-STEP-10'])
        ->and($this->saw004->partsList()->pluck('item.sku')->all())->toBe(['SP-BLADE', 'SP-SERVO-20']);

    $this->actingAs(User::factory()->operator($this->colorado)->create())
        ->get(route('machines.show', $this->saw004))
        ->assertOk()
        ->assertSee('Truss Saw 2.0')
        ->assertSee('SP-SERVO-20')
        ->assertDontSee('SP-STEP-10');
});

test('the machine page shows stock at the machine’s current site', function () {
    $blade = Item::factory()->create(['sku' => 'SP-BLADE']);
    MachineTypeItem::factory()->for($this->trussSaw)->for($blade)->create();
    DB::table('stocks')->insert([
        ['item_id' => $blade->id, 'site_id' => $this->colorado->id, 'qty' => 4, 'bin' => 'CO-A1'],
        ['item_id' => $blade->id, 'site_id' => $this->georgia->id, 'qty' => 1, 'bin' => 'GA-B2'],
    ]);

    $this->actingAs($this->admin)
        ->get(route('machines.show', $this->saw004))
        ->assertSee('CO-A1')
        ->assertDontSee('GA-B2');
});

test('criterion 33 (register part): relocating 004C keeps its history at the old site', function () {
    $blade = Item::factory()->create();
    $issue = ledgerRow($blade, $this->colorado, ['machine_id' => $this->saw004->id]);

    $this->actingAs($this->admin)
        ->put(route('machines.update', $this->saw004), truss(['sku' => '004C', 'site_id' => $this->georgia->id]))
        ->assertSessionHas('success', 'Machine relocated to BPC002. Earlier movements stay recorded at the previous site.');

    expect($this->saw004->fresh()->site_id)->toBe($this->georgia->id)
        ->and($issue->fresh()->site_id)->toBe($this->colorado->id);
});

test('SKU and type are fixed once stock has been issued to the machine', function () {
    ledgerRow(Item::factory()->create(), $this->colorado, ['machine_id' => $this->saw004->id]);

    $this->actingAs($this->admin)
        ->put(route('machines.update', $this->saw004), truss(['sku' => '014C']))
        ->assertSessionHasErrors('sku');
});
