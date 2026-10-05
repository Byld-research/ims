<?php

use App\Enums\StockCountStatus;
use App\Models\Item;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\StockCountService;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Concurrency\Concurrency;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $this->seed(DatabaseSeeder::class);
});

afterEach(function () {
    Artisan::call('migrate:fresh');
});

test('criterion 18 at the same moment: two posts of one count adjust stock exactly once', function () {
    $colorado = Site::query()->where('code', 'BPC002')->sole();
    $manager = User::factory()->manager($colorado)->create();
    $blade = Item::factory()->create(['sku' => 'SP-10001']);
    app(StockService::class)->adjust($blade, $colorado, true, '4', ReasonCode::adjustment('FOUND'), $manager, '412');

    $counts = app(StockCountService::class);
    $count = $counts->create($colorado, $manager);
    $counts->addItems($count, [$blade->id]);
    $counts->startCounting($count);
    $counts->recordCounts($count, [$count->lines()->value('id') => ['qty' => '1']]);

    $results = Concurrency::run(array_fill(0, 4, ['post-count', (string) $count->id, (string) $manager->id]));

    expect(collect($results)->where('ok', true)->count())->toBe(1, json_encode($results))
        ->and(collect($results)->where('ok', false)->pluck('error')->every(fn ($e) => str_contains($e, 'Only a count in progress can be posted')))->toBeTrue(json_encode($results))
        ->and(StockTransaction::query()->where('stock_count_id', $count->id)->count())->toBe(1)
        ->and(Stock::query()->where('item_id', $blade->id)->value('qty'))->toBe('1.000')
        ->and($count->fresh()->status)->toBe(StockCountStatus::Posted);
});
