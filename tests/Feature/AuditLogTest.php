<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->manager = User::factory()->manager($this->colorado)->create();
    $this->actingAs($this->manager);
});

test('master data changes are recorded with before and after, by whom', function () {
    $item = Item::factory()->create(['name' => 'Saw blade', 'criticality' => 'NORMAL']);

    $item->update(['name' => 'Saw blade 18"', 'criticality' => 'HIGH']);

    $log = AuditLog::query()->where('entity', 'item')->where('entity_id', $item->id)->where('action', AuditAction::Update)->sole();
    expect($log->user_id)->toBe($this->manager->id)
        ->and($log->changes)->toBe([
            'name' => ['before' => 'Saw blade', 'after' => 'Saw blade 18"'],
            'criticality' => ['before' => 'NORMAL', 'after' => 'HIGH'],
        ]);
});

test('stock movements stay in the ledger; only stock settings are audited', function () {
    $item = Item::factory()->create();
    app(StockService::class)->adjust($item, $this->colorado, true, '5', ReasonCode::adjustment('FOUND'), $this->manager, '10');

    expect(AuditLog::query()->where('entity', 'stock')->count())->toBe(0);

    $this->put(route('stock.levels.update'), ['site_id' => $this->colorado->id, 'rows' => [$item->id => ['min_level' => '3', 'bin' => 'CO-A1']]]);

    $log = AuditLog::query()->where('entity', 'stock')->sole();
    expect($log->changes)->toHaveKeys(['min_level', 'bin'])->not->toHaveKeys(['qty', 'avg_cost']);
});

test('passwords and login times never reach the audit log', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'a-new-password']);
    $user->forceFill(['last_login_at' => now()])->save();

    expect(AuditLog::query()->where('entity', 'user')->where('entity_id', $user->id)->get()->pluck('changes')->flatMap(fn ($c) => array_keys($c)))
        ->not->toContain('password')->not->toContain('last_login_at');
});

test('the audit log screen filters by record and exports CSV', function () {
    $item = Item::factory()->create(['sku' => 'SP-AUDIT']);
    $item->update(['name' => 'Renamed']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.audit.index', ['entity' => 'item', 'entity_id' => $item->id]))
        ->assertOk()
        ->assertSee('item #'.$item->id)
        ->assertSee('Renamed');

    expect($this->get(route('admin.audit.index', ['export' => 'csv']))->streamedContent())->toContain('SP-AUDIT');
});
