<?php

namespace App\Services;

use App\Enums\StockCountStatus as Status;
use App\Exceptions\StockCountException;
use App\Exceptions\StockException;
use App\Models\Item;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cycle counting (SPEC 5.6): DRAFT (lines added) → COUNTING (quantities entered) → POSTED,
 * or CANCELLED before posting. A posted count is immutable.
 *
 * Posting locks the count row first, so the same count cannot be posted twice (SPEC 13.18),
 * then each stock row in item id order.
 */
class StockCountService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumber $numbers,
    ) {}

    public function create(Site $site, User $user, ?string $scopeNote = null): StockCount
    {
        return DB::transaction(function () use ($site, $user, $scopeNote) {
            $count = new StockCount(['site_id' => $site->id, 'scope_note' => $scopeNote]);
            $count->reference = $this->numbers->next('SC', now($site->timezone)->year);
            $count->status = Status::Draft;
            $count->created_by = $user->id;
            $count->save();

            return $count;
        });
    }

    /**
     * Add items not yet on the count, snapshotting the expected quantity now (SPEC 5.6.2).
     *
     * @param  Collection<int, Item>|list<int>  $items
     * @return int number of lines added
     */
    public function addItems(StockCount $count, Collection|array $items): int
    {
        $itemIds = collect($items)->map(fn ($item) => $item instanceof Item ? $item->id : (int) $item)->unique()->values();

        return DB::transaction(function () use ($count, $itemIds) {
            $count = $this->lock($count, Status::Draft, __('Lines can be added only while the count is a draft.'));

            $new = $itemIds->diff($count->lines()->pluck('item_id'));
            $expected = Stock::query()->where('site_id', $count->site_id)->whereIn('item_id', $new)->pluck('qty', 'item_id');

            foreach ($new as $itemId) {
                $count->lines()->create(['item_id' => $itemId, 'qty_expected' => $expected->get($itemId, '0.000')]);
            }

            return $new->count();
        });
    }

    public function removeLine(StockCountLine $line): void
    {
        DB::transaction(function () use ($line) {
            $this->lock($line->stockCount, Status::Draft, __('Lines can be removed only while the count is a draft.'));
            $line->delete();
        });
    }

    public function startCounting(StockCount $count): void
    {
        DB::transaction(function () use ($count) {
            $locked = $this->lock($count, Status::Draft, __('Only a draft count can be started.'));

            if (! $locked->lines()->exists()) {
                throw new StockCountException(__('Add at least one item before counting.'));
            }

            $locked->status = Status::Counting;
            $locked->save();
        });
    }

    /**
     * Save counted quantities; an empty value means "not counted yet" (SPEC 5.6.3, 5.6.8).
     *
     * @param  array<int, array{qty: ?string, note?: ?string}>  $entries  line id => entry
     */
    public function recordCounts(StockCount $count, array $entries): void
    {
        DB::transaction(function () use ($count, $entries) {
            $count = $this->lock($count, Status::Counting, __('Quantities can be entered only while counting.'));
            $lines = $count->lines()->whereKey(array_keys($entries))->get()->keyBy('id');

            foreach ($entries as $lineId => $entry) {
                $line = $lines->get($lineId) ?? throw new StockCountException(__('A line does not belong to this count.'));
                $line->qty_counted = $entry['qty'];
                $line->note = $entry['note'] ?? $line->note;
                $line->save();
            }
        });
    }

    /**
     * What posting would do now, line by line (SPEC 5.6.5): the snapshot, the live quantity,
     * the count, and the adjustment that would be written.
     *
     * @return Collection<int, array{line: StockCountLine, live: string, moved: bool, difference: ?string, value: ?string, needs_cost: bool}>
     */
    public function preview(StockCount $count): Collection
    {
        $lines = $count->lines()->with('item')->get()->sortBy(fn ($l) => $l->item->sku)->values();
        $stocks = Stock::query()->where('site_id', $count->site_id)->whereIn('item_id', $lines->pluck('item_id'))->get()->keyBy('item_id');

        return $lines->map(function (StockCountLine $line) use ($stocks) {
            $stock = $stocks->get($line->item_id);
            $live = $stock?->qty ?? '0.000';
            $avg = $stock?->avg_cost ?? '0.0000';
            $difference = $line->qty_counted === null ? null : Decimal::round(Decimal::sub($line->qty_counted, $live), 3);

            return [
                'line' => $line,
                'live' => $live,
                'moved' => Decimal::compare($live, $line->qty_expected) !== 0,
                'difference' => $difference,
                'value' => $difference === null ? null : Decimal::round(Decimal::mul($difference, $avg), 4),
                // Stock rising from nothing with no average yet needs a cost (SPEC 5.1).
                'needs_cost' => $difference !== null && Decimal::compare($difference, '0') > 0
                    && Decimal::isZero($live) && Decimal::isZero($avg),
            ];
        });
    }

    /**
     * Post the count (SPEC 5.6.4–9).
     *
     * @param  array<int, string>  $unitCosts  line id => unit cost, for lines that need one
     * @return array{adjusted: int, unchanged: int, skipped: int}
     */
    public function post(StockCount $count, User $user, array $unitCosts = []): array
    {
        return DB::transaction(function () use ($count, $user, $unitCosts) {
            $count = $this->lock($count, Status::Counting, __('Only a count in progress can be posted. This one is :status.', [
                'status' => strtolower($count->fresh()->status->name),
            ]));

            $lines = $count->lines()->with('item')->orderBy('item_id')->get();
            $result = ['adjusted' => 0, 'unchanged' => 0, 'skipped' => 0];

            foreach ($lines as $line) {
                if ($line->qty_counted === null) {
                    $result['skipped']++;

                    continue;
                }

                try {
                    $transaction = $this->stock->countTo($line->item, $count->site, $line->qty_counted, $count, $user,
                        $unitCosts[$line->id] ?? null, $line->note);
                } catch (StockException $e) {
                    throw new StockCountException($line->item->sku.': '.$e->getMessage(), "costs.{$line->id}");
                }

                $transaction ? $result['adjusted']++ : $result['unchanged']++;
            }

            $count->status = Status::Posted;
            $count->posted_by = $user->id;
            $count->posted_at = now();
            $count->save();

            return $result;
        }, StockService::ATTEMPTS);
    }

    public function cancel(StockCount $count): void
    {
        DB::transaction(function () use ($count) {
            $locked = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isFinal()) {
                throw new StockCountException(__('A :status count cannot be cancelled.', ['status' => strtolower($locked->status->name)]));
            }

            $locked->status = Status::Cancelled;
            $locked->save();
        });
    }

    private function lock(StockCount $count, Status $required, string $message): StockCount
    {
        $locked = StockCount::query()->with('site')->whereKey($count->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== $required) {
            throw new StockCountException($message);
        }

        return $locked;
    }
}
