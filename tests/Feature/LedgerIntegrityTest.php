<?php

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Item;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->site = Site::factory()->create();
    $this->user = User::factory()->manager($this->site)->create();
    $category = Category::query()->create(['name' => 'Mechanical']);
    $this->item = Item::query()->create(['sku' => 'TEST-1', 'name' => 'Bearing', 'category_id' => $category->id, 'uom' => 'pc']);

    $this->transaction = StockTransaction::query()->create([
        'type' => TransactionType::Adjustment,
        'item_id' => $this->item->id,
        'site_id' => $this->site->id,
        'qty_delta' => '10.000',
        'unit_cost' => '5.0000',
        'value' => '50.0000',
        'qty_after' => '10.000',
        'avg_cost_after' => '5.0000',
        'user_id' => $this->user->id,
    ]);
});

test('the model refuses to update a ledger row', function () {
    $this->transaction->note = 'edited';
    $this->transaction->save();
})->throws(LogicException::class, 'append-only');

test('the model refuses to delete a ledger row', function () {
    $this->transaction->delete();
})->throws(LogicException::class, 'append-only');

test('the database refuses to update a ledger row', function () {
    DB::table('stock_transactions')->where('id', $this->transaction->id)->update(['qty_delta' => 99]);
})->throws(QueryException::class, 'append-only');

test('the database refuses to delete a ledger row', function () {
    DB::table('stock_transactions')->where('id', $this->transaction->id)->delete();
})->throws(QueryException::class, 'append-only');

test('the database refuses negative stock', function () {
    DB::table('stocks')->insert([
        'item_id' => $this->item->id,
        'site_id' => $this->site->id,
        'qty' => -1,
    ]);
})->throws(QueryException::class);

test('the database refuses a kanban flag without a bin quantity', function () {
    DB::table('stocks')->insert([
        'item_id' => $this->item->id,
        'site_id' => $this->site->id,
        'is_kanban' => true,
        'bin_qty' => null,
    ]);
})->throws(QueryException::class);

test('stock quantity and cost cannot be mass-assigned', function () {
    $stock = Stock::query()->create([
        'item_id' => $this->item->id,
        'site_id' => $this->site->id,
        'min_level' => 5,
    ]);

    expect(fn () => $stock->fill(['qty' => 100]))->toThrow(MassAssignmentException::class);
});
