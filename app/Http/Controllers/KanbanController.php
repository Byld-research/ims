<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderLine;
use App\Models\Stock;
use App\Support\CsvExport;
use App\Support\CurrentSite;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-bin (kanban) items at the selected site, those needing a refill first (SPEC 5.7; screen 13).
 * Stock stays in the item's unit; bins are shown only as a reading aid.
 */
class KanbanController extends Controller
{
    public function index(Request $request, CurrentSite $currentSite): Response
    {
        Gate::authorize('viewAny', Stock::class);

        $site = $currentSite->get();

        $stocks = Stock::query()
            ->select('stocks.*')
            ->join('items', 'items.id', '=', 'stocks.item_id')
            ->where('items.is_active', true)
            ->where('stocks.is_kanban', true)
            ->when($site, fn ($q) => $q->where('stocks.site_id', $site->id))
            ->with(['item', 'site'])
            ->orderByRaw('stocks.qty <= stocks.bin_qty DESC, stocks.qty / stocks.bin_qty ASC')
            ->orderBy('items.sku')
            ->get();

        $onOrder = PurchaseOrderLine::onOrder($stocks->pluck('item_id')->unique()->values()->all(), $site?->id);
        $bins = fn (Stock $s) => Decimal::round(Decimal::div($s->qty, $s->bin_qty), 1);

        if (CsvExport::requested($request)) {
            return CsvExport::download('two-bin-items', ['Site', 'SKU', 'Name', 'UoM', 'Location', 'Qty per bin', 'In stock', 'Bins left', 'Needs refill', 'On order'],
                $stocks->map(fn (Stock $s) => [$s->site->code, $s->item->sku, $s->item->name, $s->item->uom, $s->bin, $s->bin_qty, $s->qty,
                    $bins($s), $s->needsReplenishment(), $onOrder[$s->item_id][$s->site_id] ?? '0.000']));
        }

        return response()->view('kanban.index', compact('stocks', 'onOrder', 'site', 'bins'));
    }
}
