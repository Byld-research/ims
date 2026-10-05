<?php

use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\Site;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

function ledgerRow(Item $item, Site $site, array $attributes = []): StockTransaction
{
    // Direct ledger insert for tests that only need "this record has history".
    return StockTransaction::query()->create([
        'type' => TransactionType::Adjustment,
        'item_id' => $item->id,
        'site_id' => $site->id,
        'qty_delta' => '1.000',
        'unit_cost' => '1.0000',
        'value' => '1.0000',
        'qty_after' => '1.000',
        'avg_cost_after' => '1.0000',
        'user_id' => User::factory()->create()->id,
        ...$attributes,
    ]);
}
