<?php

use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Concurrency\Concurrency;

/*
| Opposite transfers at the same moment lock the same two stock rows. They must be taken in
| one fixed order (site id), or the two transactions deadlock each other.
*/

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $this->seed(DatabaseSeeder::class);
});

afterEach(function () {
    Artisan::call('migrate:fresh');
});

test('opposite transfers between Georgia and Colorado run concurrently without deadlock or drift', function () {
    $georgia = Site::query()->where('code', 'BPC001')->sole();
    $colorado = Site::query()->where('code', 'BPC002')->sole();
    $admin = User::factory()->admin()->create();
    $blade = Item::factory()->create(['sku' => 'SP-10001']);
    $found = ReasonCode::adjustment('FOUND');
    app(StockService::class)->adjust($blade, $georgia, true, '100', $found, $admin, '400');
    app(StockService::class)->adjust($blade, $colorado, true, '100', $found, $admin, '420');

    $georgiaToColorado = ['transfer', (string) $blade->id, (string) $georgia->id, (string) $colorado->id, '1', (string) $admin->id, '10'];
    $coloradoToGeorgia = ['transfer', (string) $blade->id, (string) $colorado->id, (string) $georgia->id, '1', (string) $admin->id, '10'];

    $results = Concurrency::run([$georgiaToColorado, $coloradoToGeorgia, $georgiaToColorado, $coloradoToGeorgia, $georgiaToColorado, $coloradoToGeorgia]);

    $at = fn (Site $site) => Stock::query()->where('item_id', $blade->id)->where('site_id', $site->id)->sole();

    expect(collect($results)->every('ok'))->toBeTrue(json_encode($results))
        ->and(StockTransaction::query()->where('type', TransactionType::TransferOut)->count())->toBe(60)
        ->and(StockTransaction::query()->where('type', TransactionType::TransferIn)->count())->toBe(60)
        ->and($at($georgia)->qty)->toBe('100.000')
        ->and($at($colorado)->qty)->toBe('100.000');

    Artisan::call('ims:verify-stock');
    expect(Artisan::output())->toContain('match the ledger');
});
