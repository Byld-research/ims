<?php

use App\Enums\TransactionType;
use App\Exceptions\StockException;
use App\Models\Category;
use App\Models\Item;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
| SPEC 5.1, 5.2 and criteria 1–6, 8 through adjustments. Receipts, issues and transfers use the
| same post() and are covered again with their own types in stages 4 and 5.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->service = app(StockService::class);
    $this->site = Site::query()->where('code', 'BPC002')->sole(); // Colorado, home of 004C
    $this->user = User::factory()->manager($this->site)->create();
    $this->item = Item::factory()->create(['uom' => 'pc']);
    $this->found = ReasonCode::adjustment('FOUND');
    $this->damage = ReasonCode::adjustment('DAMAGE');
});

function stockRow(): Stock
{
    return Stock::query()->where('item_id', test()->item->id)->where('site_id', test()->site->id)->sole();
}

function addStock(string $qty, ?string $cost = null, ?ReasonCode $reason = null): StockTransaction
{
    return test()->service->adjust(test()->item, test()->site, true, $qty, $reason ?? test()->found, test()->user, $cost);
}

function removeStock(string $qty): StockTransaction
{
    return test()->service->adjust(test()->item, test()->site, false, $qty, test()->damage, test()->user);
}

test('criteria 1–3: in at 5.00, in at 7.00, out 5 — averages and values', function () {
    $first = addStock('10', '5.00');
    expect(stockRow())->qty->toBe('10.000')->avg_cost->toBe('5.0000')
        ->and($first)->type->toBe(TransactionType::Adjustment)->value->toBe('50.0000')->qty_after->toBe('10.000');

    addStock('10', '7.00');
    expect(stockRow())->qty->toBe('20.000')->avg_cost->toBe('6.0000');

    $out = removeStock('5');
    expect(stockRow())->qty->toBe('15.000')->avg_cost->toBe('6.0000')
        ->and($out)->qty_delta->toBe('-5.000')->unit_cost->toBe('6.0000')->value->toBe('-30.0000')
        ->qty_after->toBe('15.000')->avg_cost_after->toBe('6.0000');
});

test('criterion 4 arithmetic: 10 at 4.0000 plus 5 at 6.0000 averages 4.6667, not 4.6666', function () {
    addStock('10', '4.0000');
    addStock('5', '6.0000');

    expect(stockRow()->avg_cost)->toBe('4.6667');
});

test('criterion 5: a positive adjustment into empty stock without a cost is rejected', function () {
    expect(fn () => addStock('3'))->toThrow(StockException::class, 'Enter a unit cost');

    expect(StockTransaction::query()->count())->toBe(0);
});

test('a positive adjustment without a cost enters at the current average and keeps it', function () {
    addStock('10', '6.00');
    $found = addStock('2');

    expect($found->unit_cost)->toBe('6.0000')->and(stockRow()->avg_cost)->toBe('6.0000');
});

test('the average survives an empty shelf, so a later increase without a cost uses it', function () {
    addStock('4', '12.50');
    removeStock('4');
    $found = addStock('1');

    expect(stockRow())->qty->toBe('1.000')->avg_cost->toBe('12.5000')
        ->and($found->unit_cost)->toBe('12.5000');
});

test('into empty stock the average becomes the incoming cost', function () {
    addStock('4', '10');
    removeStock('4');
    addStock('2', '3.5');

    expect(stockRow()->avg_cost)->toBe('3.5000');
});

test('criterion 6: removing more than available is rejected, names the quantity, writes nothing', function () {
    addStock('3', '9');

    expect(fn () => removeStock('3.5'))->toThrow(StockException::class, 'Only 3 pc available at BPC002.');

    expect(StockTransaction::query()->count())->toBe(1)
        ->and(stockRow()->qty)->toBe('3.000');
});

test('quantities must be positive with at most three decimals', function (string $qty) {
    addStock($qty, '1');
})->with(['0', '-1', '1.2345', 'abc', '1e3'])->throws(InvalidArgumentException::class);

test('a general-issue reason code cannot be used for an adjustment', function () {
    $this->service->adjust($this->item, $this->site, true, '1', ReasonCode::query()->where('code', 'MAINTENANCE')->sole(), $this->user, '1');
})->throws(InvalidArgumentException::class);

test('an opening balance sets last_counted_at; other reasons do not', function () {
    addStock('5', '2');
    expect(stockRow()->last_counted_at)->toBeNull();

    addStock('5', '2', ReasonCode::adjustment(ReasonCode::OPENING));
    expect(stockRow()->last_counted_at)->not->toBeNull();
});

test('a new stock row takes the category default bin', function () {
    $item = Item::factory()->for(Category::factory()->state(['default_bin' => 'SAW-SHELF-1']))->create();

    $this->service->adjust($item, $this->site, true, '1', $this->found, $this->user, '1');

    expect(Stock::query()->where('item_id', $item->id)->value('bin'))->toBe('SAW-SHELF-1');
});

test('stock is held per site: the other site is untouched', function () {
    addStock('10', '5');

    expect(Stock::query()->where('item_id', $this->item->id)->count())->toBe(1)
        ->and(Stock::query()->where('site_id', '!=', $this->site->id)->exists())->toBeFalse();
});

test('criterion 8: replaying the ledger reproduces the stocks row exactly', function () {
    mt_srand(20261005);

    for ($i = 0; $i < 150; $i++) {
        $current = currentQty();
        $qty = sprintf('%d.%03d', mt_rand(0, 40), mt_rand(0, 999));
        if ($qty === '0.000') {
            continue;
        }

        if (mt_rand(0, 2) > 0 || bccomp($current, $qty, 3) < 0) {
            mt_rand(0, 1) ? addStock($qty, sprintf('%d.%04d', mt_rand(0, 900), mt_rand(0, 9999))) : (bccomp($current, '0', 3) > 0 ? addStock($qty) : null);
        } else {
            removeStock($qty);
        }
    }

    $stock = stockRow();
    $replayed = $this->service->replay($this->item, $this->site);

    expect(StockTransaction::query()->count())->toBeGreaterThan(80)
        ->and($replayed)->toBe(['qty' => $stock->qty, 'avg_cost' => $stock->avg_cost])
        ->and(StockTransaction::query()->latest('id')->first())
        ->qty_after->toBe($stock->qty)
        ->avg_cost_after->toBe($stock->avg_cost);
});

function currentQty(): string
{
    return Stock::query()->where('item_id', test()->item->id)->where('site_id', test()->site->id)->value('qty') ?? '0';
}

test('ims:verify-stock passes on a consistent ledger and fails on a tampered stock row', function () {
    addStock('10', '4');
    removeStock('3');

    $this->artisan('ims:verify-stock')->expectsOutputToContain('All 1 stock rows match the ledger.')->assertSuccessful();

    DB::table('stocks')->where('item_id', $this->item->id)->update(['qty' => 9]);

    $this->artisan('ims:verify-stock')->expectsOutputToContain('1 of 1 stock rows differ from the ledger')->assertFailed();
});
