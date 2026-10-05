<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\MachineType;
use App\Models\PurchaseOrder;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkCenter;
use Illuminate\Support\Facades\Gate;

/*
| The permission matrix from SPEC 6, checked at policy level.
| BPC001 is the "own" site of the manager and operator below.
*/

beforeEach(function () {
    $this->own = Site::factory()->create(['code' => 'BPC001']);
    $this->other = Site::factory()->create(['code' => 'BPC002']);

    $this->users = [
        'admin' => User::factory()->admin()->create(),
        'manager' => User::factory()->manager($this->own)->create(),
        'operator' => User::factory()->operator($this->own)->create(),
    ];
});

function allows(string $role, string $ability, mixed $arguments): bool
{
    return Gate::forUser(test()->users[$role])->allows($ability, $arguments);
}

test('everyone can view stock, items, orders and work centres at every site', function (string $role) {
    $order = new PurchaseOrder(['site_id' => test()->other->id]);

    expect(allows($role, 'viewAny', Stock::class))->toBeTrue()
        ->and(allows($role, 'viewAny', Item::class))->toBeTrue()
        ->and(allows($role, 'view', $order))->toBeTrue()
        ->and(allows($role, 'viewAny', WorkCenter::class))->toBeTrue();
})->with(['admin', 'manager', 'operator']);

test('items and categories: admin and manager edit, operator does not', function (string $role, bool $expected) {
    expect(allows($role, 'create', Item::class))->toBe($expected)
        ->and(allows($role, 'update', new Item))->toBe($expected)
        ->and(allows($role, 'create', Category::class))->toBe($expected);
})->with([
    ['admin', true],
    ['manager', true],
    ['operator', false],
]);

test('site-scoped stock writes: own site only for managers, never for operators', function (string $ability, string $role, string $site, bool $expected) {
    expect(allows($role, $ability, [Stock::class, test()->{$site}]))->toBe($expected);
})->with(['setLevels', 'adjust', 'issue'])->with([
    ['admin', 'own', true],
    ['admin', 'other', true],
    ['manager', 'own', true],
    ['manager', 'other', false],
    ['operator', 'own', false],
    ['operator', 'other', false],
]);

test('transfers are entered by the receiving site', function () {
    $transfer = fn (string $role, Site $from, Site $to) => allows($role, 'transfer', [Stock::class, $from, $to]);

    expect($transfer('manager', $this->other, $this->own))->toBeTrue()
        ->and($transfer('manager', $this->own, $this->other))->toBeFalse()
        ->and($transfer('manager', $this->own, $this->own))->toBeFalse()
        ->and($transfer('admin', $this->own, $this->other))->toBeTrue()
        ->and($transfer('operator', $this->other, $this->own))->toBeFalse();
});

test('purchase orders: managers create, edit and receive at their own site only', function (string $role, string $site, bool $expected) {
    $order = new PurchaseOrder(['site_id' => test()->{$site}->id]);

    expect(allows($role, 'create', [PurchaseOrder::class, test()->{$site}]))->toBe($expected)
        ->and(allows($role, 'update', $order))->toBe($expected)
        ->and(allows($role, 'receive', $order))->toBe($expected);
})->with([
    ['admin', 'other', true],
    ['manager', 'own', true],
    ['manager', 'other', false],
    ['operator', 'own', false],
]);

test('stock counts: managers create and post at their own site only', function (string $role, string $site, bool $expected) {
    $count = new StockCount(['site_id' => test()->{$site}->id]);

    expect(allows($role, 'create', [StockCount::class, test()->{$site}]))->toBe($expected)
        ->and(allows($role, 'post', $count))->toBe($expected);
})->with([
    ['admin', 'other', true],
    ['manager', 'own', true],
    ['manager', 'other', false],
    ['operator', 'own', false],
]);

test('suppliers and machine types: admin and manager', function (string $role, bool $expected) {
    expect(allows($role, 'create', Supplier::class))->toBe($expected)
        ->and(allows($role, 'update', new Supplier))->toBe($expected)
        ->and(allows($role, 'create', MachineType::class))->toBe($expected)
        ->and(allows($role, 'update', new MachineType))->toBe($expected);
})->with([
    ['admin', true],
    ['manager', true],
    ['operator', false],
]);

test('work centres, users, reason codes and sites: admin only', function (string $role, bool $expected) {
    expect(allows($role, 'create', WorkCenter::class))->toBe($expected)
        ->and(allows($role, 'viewAny', User::class))->toBe($expected)
        ->and(allows($role, 'create', ReasonCode::class))->toBe($expected)
        ->and(allows($role, 'update', test()->own))->toBe($expected);
})->with([
    ['admin', true],
    ['manager', false],
    ['operator', false],
]);

test('a deactivated manager loses write access', function () {
    $manager = $this->users['manager'];
    $manager->is_active = false;

    expect(Gate::forUser($manager)->allows('adjust', [Stock::class, $this->own]))->toBeFalse();
});
