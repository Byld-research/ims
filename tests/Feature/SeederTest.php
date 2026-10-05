<?php

use App\Models\Category;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

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
