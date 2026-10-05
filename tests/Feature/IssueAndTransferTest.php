<?php

use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\Machine;
use App\Models\MachineTypeItem;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;

/*
| Screens 10 and 10a, criteria 21 and 24, with the Truss Saws as the case.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->saw003 = Machine::query()->where('sku', '003C')->sole();
    $this->saw004 = Machine::query()->where('sku', '004C')->sole();
    $this->coloradoManager = User::factory()->manager($this->colorado)->create();
    $this->georgiaManager = User::factory()->manager($this->georgia)->create();
    $this->blade = Item::factory()->create(['sku' => 'SP-10001', 'uom' => 'pc']);

    $found = ReasonCode::adjustment('FOUND');
    $admin = User::factory()->admin()->create();
    app(StockService::class)->adjust($this->blade, $this->colorado, true, '4', $found, $admin, '412');
    app(StockService::class)->adjust($this->blade, $this->georgia, true, '2', $found, $admin, '400');
});

function issueToSaw(array $overrides = []): array
{
    return [
        'site_id' => test()->colorado->id,
        'item_id' => test()->blade->id,
        'mode' => 'machine',
        'machine_id' => test()->saw004->id,
        'qty' => '1',
        ...$overrides,
    ];
}

function qtyAt(Site $site): string
{
    return Stock::query()->where('item_id', test()->blade->id)->where('site_id', $site->id)->value('qty');
}

test('a manager issues a blade to 004C in three steps and stays on the machine', function () {
    $this->actingAs($this->coloradoManager)
        ->post(route('issues.store'), issueToSaw(['note' => 'Blade change, shift 2']))
        ->assertRedirect(route('issues.create', ['machine' => $this->saw004->id]))
        ->assertSessionHas('success', '1 pc SP-10001 issued to 004C. 3 left at BPC002.');

    expect(qtyAt($this->colorado))->toBe('3.000')
        ->and(StockTransaction::query()->latest('id')->first())
        ->type->toBe(TransactionType::IssueMachine)->machine_id->toBe($this->saw004->id)->note->toBe('Blade change, shift 2');
});

test('the issue form offers only the active machines of the site', function () {
    $this->actingAs($this->coloradoManager)
        ->get(route('issues.create'))
        ->assertOk()
        ->assertSee('004C · Truss Saw 2.0')
        ->assertSee('008W')
        ->assertDontSee('003C · Truss Saw 1.0');
});

test('a machine at another site cannot be chosen', function () {
    $this->actingAs($this->coloradoManager)
        ->post(route('issues.store'), issueToSaw(['machine_id' => $this->saw003->id]))
        ->assertSessionHasErrors(['machine_id' => 'That machine is not active at this site.']);

    expect(qtyAt($this->colorado))->toBe('4.000');
});

test('criterion 21: a Georgia manager reads Colorado stock but cannot issue, receive or adjust it', function () {
    $this->actingAs($this->georgiaManager);

    $this->get(route('stock.index'))->assertOk()->assertSee('BPC002');
    $this->get(route('items.show', $this->blade))->assertOk()->assertSee('BPC002');
    $this->post(route('issues.store'), issueToSaw())->assertForbidden();
    $this->post(route('adjustments.store'), ['site_id' => $this->colorado->id, 'item_id' => $this->blade->id, 'direction' => 'out',
        'qty' => '1', 'reason_code_id' => ReasonCode::adjustment('DAMAGE')->id])->assertForbidden();

    expect(qtyAt($this->colorado))->toBe('4.000');
});

test('a general issue needs a general reason; "Other" needs a note', function () {
    $other = ReasonCode::query()->where('code', 'OTHER')->sole();
    $maintenance = ReasonCode::query()->where('code', 'MAINTENANCE')->sole();
    $this->actingAs($this->coloradoManager);

    $this->post(route('issues.store'), issueToSaw(['mode' => 'general', 'machine_id' => null]))->assertSessionHasErrors('reason_code_id');
    $this->post(route('issues.store'), issueToSaw(['mode' => 'general', 'reason_code_id' => $other->id]))->assertSessionHasErrors('note');
    $this->post(route('issues.store'), issueToSaw(['mode' => 'general', 'reason_code_id' => $maintenance->id]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', '1 pc SP-10001 issued to General maintenance. 3 left at BPC002.');

    expect(StockTransaction::query()->latest('id')->first())->type->toBe(TransactionType::IssueGeneral)->machine_id->toBeNull();
});

test('an issue larger than stock is refused on the form', function () {
    $this->actingAs($this->coloradoManager)
        ->post(route('issues.store'), issueToSaw(['qty' => '5']))
        ->assertSessionHasErrors(['qty' => 'Only 4 pc available at BPC002.']);
});

test('criterion 24: Colorado enters a transfer from Georgia into Colorado, not the other way', function () {
    $this->actingAs($this->coloradoManager);

    $this->post(route('transfers.store'), ['from_site_id' => $this->georgia->id, 'to_site_id' => $this->colorado->id, 'item_id' => $this->blade->id, 'qty' => '1'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', '1 pc SP-10001 transferred from BPC001 to BPC002. Now 5 at BPC002.');

    $this->post(route('transfers.store'), ['from_site_id' => $this->colorado->id, 'to_site_id' => $this->georgia->id, 'item_id' => $this->blade->id, 'qty' => '1'])
        ->assertForbidden();

    expect(qtyAt($this->colorado))->toBe('5.000')->and(qtyAt($this->georgia))->toBe('1.000');
});

test('criterion 25 on the form: the sender’s shortfall is explained', function () {
    $this->actingAs($this->coloradoManager)
        ->post(route('transfers.store'), ['from_site_id' => $this->georgia->id, 'to_site_id' => $this->colorado->id, 'item_id' => $this->blade->id, 'qty' => '3'])
        ->assertSessionHasErrors(['qty' => 'Only 2 pc available at BPC001. BPC001 must first correct its recorded stock with an adjustment; then enter the transfer again.']);

    expect(StockTransaction::query()->count())->toBe(2);
});

test('the transfer form fixes the receiving site to the manager’s own', function () {
    $this->actingAs($this->coloradoManager)
        ->get(route('transfers.create'))
        ->assertOk()
        ->assertSee('BPC002 · BPC Colorado')
        ->assertSee('name="to_site_id" value="'.$this->colorado->id.'"', false);
});

test('operators cannot open the issue or transfer forms, and see no Issue menu entry', function () {
    $operator = User::factory()->operator($this->colorado)->create();

    $this->actingAs($operator)->get(route('issues.create'))->assertForbidden();
    $this->actingAs($operator)->get(route('transfers.create'))->assertForbidden();
    $this->actingAs($operator)->get(route('stock.index'))->assertDontSee(route('issues.create'));
    $this->actingAs($this->coloradoManager)->get(route('stock.index'))->assertSee(route('issues.create'));
});

test('the 004C page offers issuing per part and shows its consumption history', function () {
    MachineTypeItem::factory()->for($this->saw004->machineType)->for($this->blade)->create();
    $this->actingAs($this->coloradoManager)->post(route('issues.store'), issueToSaw(['qty' => '2', 'note' => 'Blade change']));

    $this->get(route('machines.show', $this->saw004))
        ->assertOk()
        ->assertSee(route('issues.create', ['machine' => $this->saw004->id, 'item' => $this->blade->id]))
        ->assertSee('Consumption history')
        ->assertSee('Blade change')
        ->assertSee('Last 90 days: 1 issues, 824.00 USD');

    // Georgia's manager sees the history but cannot issue to a Colorado machine.
    $this->actingAs($this->georgiaManager)->get(route('machines.show', $this->saw004))
        ->assertOk()
        ->assertSee('Blade change')
        ->assertDontSee('Issue to 004C');
});
