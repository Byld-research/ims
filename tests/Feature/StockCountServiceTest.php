<?php

use App\Enums\StockCountStatus as Status;
use App\Enums\TransactionType;
use App\Exceptions\StockCountException;
use App\Models\Item;
use App\Models\Machine;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\StockCountService;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;

/*
| SPEC 5.6 and criteria 16–18. Case: Colorado (BPC002) counts the Truss Saw spares for 004C.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->counts = app(StockCountService::class);
    $this->stock = app(StockService::class);
    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->user = User::factory()->manager($this->colorado)->create();
    $this->blade = Item::factory()->create(['sku' => 'SP-10001', 'uom' => 'pc', 'criticality' => 'A']);
    $this->filter = Item::factory()->create(['sku' => 'CS-30001', 'uom' => 'pc', 'criticality' => 'C']);
    $found = ReasonCode::adjustment('FOUND');
    $this->stock->adjust($this->blade, $this->colorado, true, '4', $found, $this->user, '412');
    $this->stock->adjust($this->filter, $this->colorado, true, '10', $found, $this->user, '41.3');

    $this->count = $this->counts->create($this->colorado, $this->user, 'Truss Saw spares');
    $this->counts->addItems($this->count, [$this->blade->id, $this->filter->id]);
});

function line(Item $item)
{
    return test()->count->lines()->where('item_id', $item->id)->sole();
}

function countAndPost(array $counted, array $costs = []): array
{
    test()->counts->startCounting(test()->count);
    test()->counts->recordCounts(test()->count, collect($counted)
        ->mapWithKeys(fn ($qty, $itemId) => [Item::find($itemId)->stockCountLines()->where('stock_count_id', test()->count->id)->value('id') => ['qty' => $qty]])
        ->all());

    return test()->counts->post(test()->count, test()->user, $costs);
}

function stockOf(Item $item): Stock
{
    return Stock::query()->where('item_id', $item->id)->where('site_id', test()->colorado->id)->sole();
}

test('a count gets a yearly reference and snapshots the expected quantity per line', function () {
    expect($this->count)->reference->toBe('SC-'.now('America/Denver')->year.'-0001')->status->toBe(Status::Draft)
        ->and(line($this->blade)->qty_expected)->toBe('4.000')
        ->and(line($this->filter)->qty_expected)->toBe('10.000');

    expect($this->counts->addItems($this->count, [$this->blade->id]))->toBe(0); // already on the count
});

test('criterion 16: counted equal to stock writes nothing but stamps last_counted_at', function () {
    $result = countAndPost([$this->blade->id => '4']);

    expect($result)->toBe(['adjusted' => 0, 'unchanged' => 1, 'skipped' => 1])
        ->and(StockTransaction::query()->where('stock_count_id', $this->count->id)->count())->toBe(0)
        ->and(stockOf($this->blade)->last_counted_at)->not->toBeNull();
});

test('criterion 17: counted lower writes a COUNT adjustment of the negative difference', function () {
    countAndPost([$this->blade->id => '3', $this->filter->id => '10']);

    $adjustment = StockTransaction::query()->where('stock_count_id', $this->count->id)->sole();

    expect($adjustment)
        ->type->toBe(TransactionType::Adjustment)
        ->reasonCode->code->toBe('COUNT')
        ->item_id->toBe($this->blade->id)
        ->qty_delta->toBe('-1.000')
        ->value->toBe('-412.0000')
        ->and(stockOf($this->blade)->qty)->toBe('3.000')
        ->and($this->count->fresh())->status->toBe(Status::Posted)->posted_by->toBe($this->user->id);
});

test('counted higher adds stock at the current average', function () {
    countAndPost([$this->filter->id => '12.5']);

    expect(stockOf($this->filter))->qty->toBe('12.500')->avg_cost->toBe('41.3000');
});

test('posting compares with the live quantity, not the snapshot, when stock moved during counting', function () {
    $this->counts->startCounting($this->count);
    $this->counts->recordCounts($this->count, [line($this->blade)->id => ['qty' => '2']]);

    // While counting, a blade was issued to 004C: live stock is 3, the snapshot still says 4.
    $this->stock->issueToMachine($this->blade, Machine::query()->where('sku', '004C')->sole(), '1', $this->user);

    $preview = $this->counts->preview($this->count)->firstWhere('line.item_id', $this->blade->id);
    expect($preview)->live->toBe('3.000')->moved->toBeTrue()->difference->toBe('-1.000');

    $this->counts->post($this->count, $this->user);

    expect(stockOf($this->blade)->qty)->toBe('2.000')
        ->and(StockTransaction::query()->where('stock_count_id', $this->count->id)->value('qty_delta'))->toBe('-1.000');
});

test('uncounted lines are skipped: no movement and no last_counted_at (criterion 26)', function () {
    $result = countAndPost([$this->blade->id => '4']);

    expect($result['skipped'])->toBe(1)->and(stockOf($this->filter)->last_counted_at)->toBeNull();
});

test('criterion 18: a posted count cannot be edited, posted again or cancelled', function () {
    countAndPost([$this->blade->id => '3']);

    expect(fn () => $this->counts->post($this->count, $this->user))->toThrow(StockCountException::class, 'Only a count in progress can be posted')
        ->and(fn () => $this->counts->recordCounts($this->count, [line($this->blade)->id => ['qty' => '1']]))->toThrow(StockCountException::class)
        ->and(fn () => $this->counts->addItems($this->count, [Item::factory()->create()->id]))->toThrow(StockCountException::class)
        ->and(fn () => $this->counts->cancel($this->count))->toThrow(StockCountException::class)
        ->and(StockTransaction::query()->where('stock_count_id', $this->count->id)->count())->toBe(1);
});

test('a cancelled count writes nothing and cannot be posted', function () {
    $this->counts->startCounting($this->count);
    $this->counts->recordCounts($this->count, [line($this->blade)->id => ['qty' => '1']]);
    $this->counts->cancel($this->count);

    expect(fn () => $this->counts->post($this->count, $this->user))->toThrow(StockCountException::class)
        ->and(StockTransaction::query()->where('stock_count_id', $this->count->id)->count())->toBe(0)
        ->and(stockOf($this->blade)->qty)->toBe('4.000');
});

test('lines change only in draft; counts are entered only while counting', function () {
    expect(fn () => $this->counts->recordCounts($this->count, [line($this->blade)->id => ['qty' => '1']]))->toThrow(StockCountException::class);

    $this->counts->startCounting($this->count);

    expect(fn () => $this->counts->addItems($this->count, [Item::factory()->create()->id]))->toThrow(StockCountException::class)
        ->and(fn () => $this->counts->removeLine(line($this->blade)))->toThrow(StockCountException::class);
});

test('an empty count cannot be started', function () {
    $empty = $this->counts->create($this->colorado, $this->user);

    expect(fn () => $this->counts->startCounting($empty))->toThrow(StockCountException::class, 'Add at least one item');
});

test('finding stock of an item with no cost yet needs a unit cost', function () {
    $servo = Item::factory()->create(['sku' => 'SP-10005']);
    $this->counts->addItems($this->count, [$servo->id]);

    $this->counts->startCounting($this->count);
    $this->counts->recordCounts($this->count, [line($servo)->id => ['qty' => '1']]);

    expect($this->counts->preview($this->count)->firstWhere('line.item_id', $servo->id)['needs_cost'])->toBeTrue()
        ->and(fn () => $this->counts->post($this->count, $this->user))->toThrow(StockCountException::class, 'SP-10005: Enter a unit cost');

    expect($this->count->fresh()->status)->toBe(Status::Counting)
        ->and(StockTransaction::query()->where('stock_count_id', $this->count->id)->count())->toBe(0);

    $this->counts->post($this->count, $this->user, [line($servo)->id => '238']);

    expect(stockOf($servo))->qty->toBe('1.000')->avg_cost->toBe('238.0000');
});

test('items due for counting follow the suggested frequencies', function () {
    $kanban = Item::factory()->create(['criticality' => null]);
    $unclassified = Item::factory()->create(['criticality' => null]);
    foreach ([$kanban, $unclassified] as $item) {
        $this->stock->adjust($item, $this->colorado, true, '5', ReasonCode::adjustment('FOUND'), $this->user, '1');
    }
    Stock::query()->where('item_id', $kanban->id)->update(['is_kanban' => true, 'bin_qty' => 2]);

    // Never counted: A, C and kanban are due; unclassified non-kanban has no frequency.
    $due = fn () => Stock::query()->where('stocks.site_id', $this->colorado->id)->dueForCount()->pluck('item_id')->sort()->values()->all();
    expect($due())->toBe(collect([$this->blade->id, $this->filter->id, $kanban->id])->sort()->values()->all());

    // Counted 40 days ago: class A (monthly) is due again, class C and kanban (quarterly) are not.
    Stock::query()->where('site_id', $this->colorado->id)->update(['last_counted_at' => now()->subDays(40)]);
    expect($due())->toBe([$this->blade->id]);
});
