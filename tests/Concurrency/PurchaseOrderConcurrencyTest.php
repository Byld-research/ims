<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Concurrency\Concurrency;

/*
| SPEC 13.7 and 13.15 with real parallel processes. Pest's transaction-wrapped tests cannot
| exercise row locks, so these tests commit data and rebuild the test database afterwards.
*/

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $this->seed(DatabaseSeeder::class);

    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->manager = User::factory()->manager($this->colorado)->create();
    $this->supplier = Supplier::factory()->create(['name' => 'Kraków warehouse']);
    $this->blade = Item::factory()->create(['sku' => 'SP-10001']);
});

afterEach(function () {
    Artisan::call('migrate:fresh');
});

test('criterion 7: concurrent receipts against the same line are both recorded, none double-counted', function () {
    $orders = app(PurchaseOrderService::class);
    $order = $orders->create($this->supplier, $this->colorado, $this->manager);
    $line = $orders->addLine($order, $this->blade, '10', '5.00');
    $orders->markOrdered($order);

    $results = Concurrency::run([
        ['receive', (string) $order->id, (string) $line->id, '6', (string) $this->manager->id],
        ['receive', (string) $order->id, (string) $line->id, '4', (string) $this->manager->id],
    ]);

    expect(collect($results)->pluck('ok')->all())->toBe([true, true], json_encode($results))
        ->and(StockTransaction::query()->where('type', TransactionType::Receipt)->count())->toBe(2)
        ->and($line->fresh()->qty_received)->toBe('10.000')
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and(Stock::query()->where('item_id', $this->blade->id)->sole())->qty->toBe('10.000')->avg_cost->toBe('5.0000');

    Artisan::call('ims:verify-stock');
    expect(Artisan::output())->toContain('match the ledger');
});

test('criterion 7 under load: eight receipts at once against one line', function () {
    $orders = app(PurchaseOrderService::class);
    $order = $orders->create($this->supplier, $this->colorado, $this->manager);
    $line = $orders->addLine($order, $this->blade, '100', '5.00');
    $orders->markOrdered($order);

    $results = Concurrency::run(array_fill(0, 8, ['receive', (string) $order->id, (string) $line->id, '5', (string) $this->manager->id]));

    expect(collect($results)->where('ok', true)->count())->toBe(8, json_encode($results))
        ->and($line->fresh()->qty_received)->toBe('40.000')
        ->and(StockTransaction::query()->count())->toBe(8)
        ->and(StockTransaction::query()->orderBy('id')->pluck('qty_after')->all())
        ->toBe(['5.000', '10.000', '15.000', '20.000', '25.000', '30.000', '35.000', '40.000'])
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived);
});

test('criterion 15: orders created at the same moment get distinct consecutive numbers', function () {
    $results = Concurrency::run(array_fill(0, 6, ['number', (string) $this->supplier->id, (string) $this->colorado->id, (string) $this->manager->id]));

    $numbers = collect($results)->pluck('result')->sort()->values()->all();
    $year = now('America/Denver')->year;

    expect(collect($results)->every('ok'))->toBeTrue(json_encode($results))
        ->and($numbers)->toBe(array_map(fn ($n) => sprintf('PO-%d-%04d', $year, $n), range(1, 6)))
        ->and(PurchaseOrder::query()->count())->toBe(6);
});
