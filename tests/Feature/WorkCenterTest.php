<?php

use App\Models\MachineTypeItem;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkCenter;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->colorado = Site::factory()->create(['code' => 'BPC001']);
    $this->georgia = Site::factory()->create(['code' => 'BPC002']);
    $this->admin = User::factory()->admin()->create();
});

test('only administrators manage work centres', function () {
    $payload = ['site_id' => $this->colorado->id, 'code' => '004C', 'name' => 'Truss Saw 004C'];

    $this->actingAs(User::factory()->manager($this->colorado)->create())
        ->post(route('work-centers.store'), $payload)
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('work-centers.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(WorkCenter::query()->sole()->name)->toBe('Truss Saw 004C');
});

test('codes are unique per site, not globally', function () {
    WorkCenter::factory()->create(['site_id' => $this->colorado->id, 'code' => '004C']);

    $this->actingAs($this->admin);
    $this->post(route('work-centers.store'), ['site_id' => $this->colorado->id, 'code' => '004C', 'name' => 'Dup'])
        ->assertSessionHasErrors('code');
    $this->post(route('work-centers.store'), ['site_id' => $this->georgia->id, 'code' => '004C', 'name' => 'Same code, other site'])
        ->assertSessionHasNoErrors();
});

test('a work centre cannot change site once stock was issued to it', function () {
    $workCenter = WorkCenter::factory()->create(['site_id' => $this->colorado->id]);
    $line = MachineTypeItem::factory()->create();
    ledgerRow($line->item, $this->colorado, ['work_center_id' => $workCenter->id]);

    $this->actingAs($this->admin)
        ->put(route('work-centers.update', $workCenter), ['site_id' => $this->georgia->id, 'code' => $workCenter->code, 'name' => 'Moved'])
        ->assertSessionHasErrors('site_id');
});

test('the list shows the current site only', function () {
    WorkCenter::factory()->create(['site_id' => $this->colorado->id, 'code' => 'CO-1']);
    WorkCenter::factory()->create(['site_id' => $this->georgia->id, 'code' => 'GA-1']);

    $this->actingAs(User::factory()->operator($this->georgia)->create())
        ->get(route('work-centers.index'))
        ->assertOk()
        ->assertSee('GA-1')
        ->assertDontSee('CO-1');
});

test('the detail page shows the parts list with stock at the work centre site', function () {
    $line = MachineTypeItem::factory()->create(['reference' => 'POS-7']);
    $workCenter = WorkCenter::factory()->create(['site_id' => $this->georgia->id, 'machine_type_id' => $line->machine_type_id]);
    DB::table('stocks')->insert([
        ['item_id' => $line->item_id, 'site_id' => $this->georgia->id, 'qty' => 7, 'bin' => 'G-07'],
        ['item_id' => $line->item_id, 'site_id' => $this->colorado->id, 'qty' => 99, 'bin' => 'C-99'],
    ]);

    // Readable from the other site too (SPEC 6).
    $this->actingAs(User::factory()->operator($this->colorado)->create())
        ->get(route('work-centers.show', $workCenter))
        ->assertOk()
        ->assertSee($line->item->sku)
        ->assertSee('POS-7')
        ->assertSee('G-07')
        ->assertDontSee('C-99');
});

test('a work centre without a machine type has no parts list', function () {
    $workCenter = WorkCenter::factory()->create(['site_id' => $this->colorado->id]);

    $this->actingAs($this->admin)
        ->get(route('work-centers.show', $workCenter))
        ->assertOk()
        ->assertSee('No machine type assigned');
});
