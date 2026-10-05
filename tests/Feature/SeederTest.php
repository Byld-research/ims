<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\MachineTypeItem;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\SupplierItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;

test('seeders are idempotent', function () {
    config(['ims.seed.admin_password' => 'secret', 'ims.seed.manager_password' => 'secret']);

    $this->seed(DatabaseSeeder::class);
    $first = [Site::count(), Category::count(), ReasonCode::count(), User::count()];

    $this->seed(DatabaseSeeder::class);

    expect([Site::count(), Category::count(), ReasonCode::count(), User::count()])
        ->toBe($first)
        ->toBe([2, 22, 10, 3]);
});

test('seeded reference data matches the specification', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Site::query()->where('code', 'BPC001')->value('timezone'))->toBe('America/Denver')
        ->and(Site::query()->where('code', 'BPC002')->value('timezone'))->toBe('America/New_York')
        ->and(ReasonCode::adjustment(ReasonCode::OPENING)->label)->toBe('Opening balance')
        ->and(Category::query()->where('is_structural', true)->count())->toBe(5)
        ->and(Category::query()->where('name', 'Die Blades')->first()->parent->name)->toBe('Wear Parts');
});

test('development users are skipped when no passwords are configured', function () {
    config(['ims.seed.admin_password' => null, 'ims.seed.manager_password' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(0);
});

test('the demo seeder builds a consistent catalogue and is idempotent', function () {
    $this->seed(DemoDataSeeder::class);
    $first = [Item::count(), SupplierItem::count(), MachineTypeItem::count(), Machine::count()];

    $this->seed(DemoDataSeeder::class);

    expect([Item::count(), SupplierItem::count(), MachineTypeItem::count(), Machine::count()])
        ->toBe($first)
        ->and(Item::query()->whereRelation('category', 'is_structural', true)->count())->toBe(0);
});

test('the machine register matches the specification', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $types = MachineType::query()->orderBy('code')->pluck('last_serial', 'code')->all();

    expect($types)->toBe(['A' => 7, 'B' => 6, 'C' => 6, 'D' => 1, 'H' => 0, 'S' => 0, 'T' => 0, 'W' => 9])
        ->and(Machine::count())->toBe(10)
        ->and(Machine::query()->where('sku', '003C')->first())
        ->revision->toBe('1.0')
        ->site->code->toBe('BPC002')
        ->and(Machine::query()->where('sku', '004C')->first())
        ->revision->toBe('2.0')
        ->site->code->toBe('BPC001')
        ->and(Machine::query()->whereIn('sku', ['005C', '006C', '006A', '007A', '001D', '005B', '006B'])->count())->toBe(0);
});

test('the machine register seeder never lowers a serial counter', function () {
    $this->seed(DatabaseSeeder::class);
    MachineType::query()->where('code', 'C')->update(['last_serial' => 12]);

    $this->seed(DatabaseSeeder::class);

    expect(MachineType::query()->where('code', 'C')->value('last_serial'))->toBe(12);
});
