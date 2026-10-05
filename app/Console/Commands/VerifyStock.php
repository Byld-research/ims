<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Replays the ledger for every stock row and reports any difference (SPEC 4.6, 13.8).
 * Exit code 1 when a mismatch is found, so it can run from cron or CI.
 */
#[Signature('ims:verify-stock')]
#[Description('Check that every stocks row equals a replay of stock_transactions')]
class VerifyStock extends Command
{
    public function handle(StockService $service): int
    {
        $mismatches = [];
        $checked = 0;

        Stock::query()->with(['item', 'site'])->orderBy('id')->chunk(500, function ($stocks) use ($service, &$mismatches, &$checked) {
            foreach ($stocks as $stock) {
                $checked++;
                $replayed = $service->replay($stock->item, $stock->site);

                if ($replayed['qty'] !== $stock->qty || $replayed['avg_cost'] !== $stock->avg_cost) {
                    $mismatches[] = [$stock->item->sku, $stock->site->code, $stock->qty, $replayed['qty'], $stock->avg_cost, $replayed['avg_cost']];
                }
            }
        });

        if ($mismatches) {
            $this->error(count($mismatches).' of '.$checked.' stock rows differ from the ledger:');
            $this->table(['SKU', 'Site', 'Qty', 'Ledger qty', 'Avg cost', 'Ledger avg'], $mismatches);

            return self::FAILURE;
        }

        $this->info("All {$checked} stock rows match the ledger.");

        return self::SUCCESS;
    }
}
