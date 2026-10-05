<?php

use App\Enums\TransactionType;
use App\Exceptions\StockException;
use App\Models\Item;
use App\Models\Machine;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;

/*
| SPEC 5.1, 5.2, 5.8 and criteria 3, 4, 6, 25, 33. Case: Truss Saw 004C (rev 2.0) in Colorado
| (BPC002) and 003C (rev 1.0) in Georgia (BPC001).
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->stock = app(StockService::class);
    $this->georgia = Site::query()->where('code', 'BPC001')->sole();
    $this->colorado = Site::query()->where('code', 'BPC002')->sole();
    $this->saw004 = Machine::query()->where('sku', '004C')->sole();
    $this->user = User::factory()->admin()->create();
    $this->blade = Item::factory()->create(['sku' => 'SP-10001', 'uom' => 'pc']);
    $this->found = ReasonCode::adjustment('FOUND');
});

function put(Site $site, string $qty, string $cost): void
{
    test()->stock->adjust(test()->blade, $site, true, $qty, test()->found, test()->user, $cost);
}

function at(Site $site): Stock
{
    return Stock::query()->where('item_id', test()->blade->id)->where('site_id', $site->id)->sole();
}

test('criterion 3: issuing 5 to 004C leaves 15 at the unchanged average and a −30.0000 movement', function () {
    put($this->colorado, '10', '5');
    put($this->colorado, '10', '7');

    $issue = $this->stock->issueToMachine($this->blade, $this->saw004, '5', $this->user, 'Blade change');

    expect(at($this->colorado))->qty->toBe('15.000')->avg_cost->toBe('6.0000')
        ->and($issue)
        ->type->toBe(TransactionType::IssueMachine)
        ->machine_id->toBe($this->saw004->id)
        ->site_id->toBe($this->colorado->id)
        ->qty_delta->toBe('-5.000')
        ->unit_cost->toBe('6.0000')
        ->value->toBe('-30.0000');
});

test('criterion 4: 5 from Colorado at 6.0000 into Georgia holding 10 at 4.0000', function () {
    put($this->colorado, '20', '6');
    put($this->georgia, '10', '4');

    ['out' => $out, 'in' => $in] = $this->stock->transfer($this->blade, $this->colorado, $this->georgia, '5', $this->user);

    expect(at($this->colorado))->qty->toBe('15.000')->avg_cost->toBe('6.0000')
        ->and(at($this->georgia))->qty->toBe('15.000')->avg_cost->toBe('4.6667')
        ->and($out)->type->toBe(TransactionType::TransferOut)->value->toBe('-30.0000')->counter_site_id->toBe($this->georgia->id)
        ->and($in)->type->toBe(TransactionType::TransferIn)->unit_cost->toBe('6.0000')->value->toBe('30.0000')->counter_site_id->toBe($this->colorado->id)
        ->and($in->transfer_group)->toBe($out->transfer_group)->not->toBeNull();
});

test('a transfer into empty stock carries the sender’s average as the receiver’s cost', function () {
    put($this->colorado, '4', '412');

    $this->stock->transfer($this->blade, $this->colorado, $this->georgia, '1', $this->user);

    expect(at($this->georgia))->qty->toBe('1.000')->avg_cost->toBe('412.0000');
});

test('criterion 6: an issue larger than available is rejected and writes nothing', function () {
    put($this->colorado, '2', '5');

    expect(fn () => $this->stock->issueToMachine($this->blade, $this->saw004, '3', $this->user))
        ->toThrow(StockException::class, 'Only 2 pc available at BPC002.');

    expect(StockTransaction::query()->count())->toBe(1);
});

test('criterion 25: a transfer exceeding the sender’s stock names it, says who must fix it, and writes nothing at either site', function () {
    put($this->georgia, '1', '400');

    expect(fn () => $this->stock->transfer($this->blade, $this->georgia, $this->colorado, '2', $this->user))
        ->toThrow(StockException::class, 'Only 1 pc available at BPC001. BPC001 must first correct its recorded stock with an adjustment');

    expect(StockTransaction::query()->count())->toBe(1)
        ->and(Stock::query()->where('site_id', $this->colorado->id)->where('qty', '>', 0)->exists())->toBeFalse();
});

test('criterion 33: 004C takes stock at BPC002; after relocation to BPC001 at BPC001, history stays', function () {
    put($this->colorado, '5', '10');
    put($this->georgia, '5', '10');

    $before = $this->stock->issueToMachine($this->blade, $this->saw004, '1', $this->user);
    expect($before->site_id)->toBe($this->colorado->id);

    $this->saw004->update(['site_id' => $this->georgia->id]);

    $after = $this->stock->issueToMachine($this->blade, $this->saw004->fresh(), '1', $this->user);

    expect($after->site_id)->toBe($this->georgia->id)
        ->and($before->fresh()->site_id)->toBe($this->colorado->id)
        ->and(at($this->colorado)->qty)->toBe('4.000')
        ->and(at($this->georgia)->qty)->toBe('4.000');
});

test('the site comes from the machine as it is now, not as the caller last saw it', function () {
    put($this->georgia, '5', '10');
    $stale = $this->saw004->fresh();
    Machine::query()->whereKey($this->saw004->id)->update(['site_id' => $this->georgia->id]);

    $issue = $this->stock->issueToMachine($this->blade, $stale, '1', $this->user);

    expect($issue->site_id)->toBe($this->georgia->id);
});

test('an inactive machine takes no stock', function () {
    put($this->colorado, '5', '10');
    $this->saw004->update(['is_active' => false]);

    expect(fn () => $this->stock->issueToMachine($this->blade, $this->saw004, '1', $this->user))
        ->toThrow(StockException::class, 'Machine 004C is inactive');
});

test('a general issue needs a general-issue reason and is valued at the average', function () {
    put($this->colorado, '10', '2.5');
    $maintenance = ReasonCode::query()->where('code', 'MAINTENANCE')->sole();

    $issue = $this->stock->issueGeneral($this->blade, $this->colorado, '4', $maintenance, $this->user, 'Workshop bench grinder');

    expect($issue)->type->toBe(TransactionType::IssueGeneral)->reason_code_id->toBe($maintenance->id)->value->toBe('-10.0000')->machine_id->toBeNull()
        ->and(fn () => $this->stock->issueGeneral($this->blade, $this->colorado, '1', $this->found, $this->user))->toThrow(InvalidArgumentException::class);
});

test('criterion 8 across every movement type: replay matches both sites', function () {
    put($this->colorado, '20', '6');
    put($this->georgia, '10', '4');
    $this->stock->issueToMachine($this->blade, $this->saw004, '3', $this->user);
    $this->stock->transfer($this->blade, $this->colorado, $this->georgia, '5', $this->user);
    $this->stock->issueGeneral($this->blade, $this->georgia, '2.5', ReasonCode::query()->where('code', 'SAMPLE')->sole(), $this->user);
    $this->stock->transfer($this->blade, $this->georgia, $this->colorado, '1.25', $this->user);

    foreach ([$this->colorado, $this->georgia] as $site) {
        expect($this->stock->replay($this->blade, $site))->toBe(['qty' => at($site)->qty, 'avg_cost' => at($site)->avg_cost]);
    }
});
