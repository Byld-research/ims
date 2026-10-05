<?php

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Site;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\StockCountService;
use App\Services\StockService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/*
| A separate PHP process for concurrency tests. Boots the app against the test database,
| waits until a shared start time, then performs one action.
|
|   php worker.php receive <order_id> <line_id> <qty> <user_id> <start_at>
|   php worker.php number <supplier_id> <site_id> <user_id> <start_at>
|   php worker.php post-count <stock_count_id> <user_id> <start_at>
|   php worker.php transfer <item_id> <from_site_id> <to_site_id> <qty> <user_id> <times> <start_at>
|
| Prints a JSON line with the outcome.
*/

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $action] = $argv;
$startAt = (float) end($argv);

// Warm up the connection before the barrier, so all workers fire together.
DB::select('SELECT 1');
usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));

try {
    $result = match ($action) {
        'receive' => (function () use ($argv) {
            [, , $orderId, $lineId, $qty, $userId] = $argv;
            app(PurchaseOrderService::class)->receive(
                PurchaseOrder::query()->findOrFail($orderId),
                [(int) $lineId => $qty],
                User::query()->findOrFail($userId),
            );

            return 'ok';
        })(),
        'transfer' => (function () use ($argv) {
            [, , $itemId, $fromId, $toId, $qty, $userId, $times] = $argv;
            $item = Item::query()->findOrFail($itemId);
            $from = Site::query()->findOrFail($fromId);
            $to = Site::query()->findOrFail($toId);
            $user = User::query()->findOrFail($userId);

            for ($i = 0; $i < (int) $times; $i++) {
                app(StockService::class)->transfer($item, $from, $to, $qty, $user);
            }

            return 'ok';
        })(),
        'post-count' => (function () use ($argv) {
            [, , $countId, $userId] = $argv;
            $result = app(StockCountService::class)->post(
                StockCount::query()->findOrFail($countId),
                User::query()->findOrFail($userId),
            );

            return json_encode($result);
        })(),
        'number' => (function () use ($argv) {
            [, , $supplierId, $siteId, $userId] = $argv;

            return app(PurchaseOrderService::class)->create(
                Supplier::query()->findOrFail($supplierId),
                Site::query()->findOrFail($siteId),
                User::query()->findOrFail($userId),
            )->number;
        })(),
    };

    echo json_encode(['ok' => true, 'result' => $result]), PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => get_class($e).': '.$e->getMessage()]), PHP_EOL;
    exit(1);
}
