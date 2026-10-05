<?php

namespace App\Support;

use App\Models\StockTransaction;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV of ledger rows, shared by the item and machine history lists.
 */
final class LedgerCsv
{
    public static function download(string $basename, Builder $transactions): StreamedResponse
    {
        return CsvExport::download($basename,
            ['When (UTC)', 'Site', 'Type', 'SKU', 'Item', 'Qty', 'Unit cost', 'Value', 'Qty after', 'Avg cost after',
                'Machine', 'Counter site', 'Order', 'Reason', 'Count', 'Note', 'User'],
            $transactions->clone()
                ->with(['item', 'site', 'machine', 'counterSite', 'purchaseOrderLine.purchaseOrder', 'reasonCode', 'stockCount', 'user'])
                ->orderBy('id')
                ->lazy(500)
                ->map(fn (StockTransaction $t) => [
                    $t->created_at->utc()->format('Y-m-d H:i:s'), $t->site->code, $t->type, $t->item->sku, $t->item->name,
                    $t->qty_delta, $t->unit_cost, $t->value, $t->qty_after, $t->avg_cost_after,
                    $t->machine?->sku, $t->counterSite?->code, $t->purchaseOrderLine?->purchaseOrder?->number,
                    $t->reasonCode?->code, $t->stockCount?->reference, $t->note, $t->user->name,
                ]));
    }
}
