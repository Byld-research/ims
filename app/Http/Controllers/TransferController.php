<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Http\Requests\TransferRequest;
use App\Models\Item;
use App\Models\Site;
use App\Models\Stock;
use App\Services\StockService;
use App\Support\CurrentSite;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Transfer in from another site, entered by the receiving manager when the goods arrive
 * (SPEC 3.7, 5.2; screen 10a). There is no in-transit state.
 */
class TransferController extends Controller
{
    public function create(Request $request, CurrentSite $currentSite): View
    {
        $sites = Site::query()->active()->orderBy('code')->get();

        // Receiving sites: those where the user may record stock.
        $receiving = $sites->filter(fn (Site $site) => $request->user()->can('adjust', [Stock::class, $site]));

        abort_if($receiving->isEmpty() || $sites->count() < 2, 403);

        $toId = $receiving->firstWhere('id', $currentSite->id())?->id ?? ($receiving->count() === 1 ? $receiving->first()->id : null);

        return view('transfers.create', [
            'sites' => $sites,
            'receiving' => $receiving,
            'toId' => $toId,
            'fromId' => $request->integer('from') ?: $sites->firstWhere('id', '!=', $toId)?->id,
            'item' => Item::query()->find($request->integer('item')),
        ]);
    }

    public function store(TransferRequest $request, StockService $stock): RedirectResponse
    {
        $item = Item::query()->findOrFail($request->validated('item_id'));
        $from = Site::query()->findOrFail($request->validated('from_site_id'));
        $to = Site::query()->findOrFail($request->validated('to_site_id'));

        try {
            ['in' => $in] = $stock->transfer($item, $from, $to, $request->validated('qty'), $request->user(), $request->validated('note'));
        } catch (StockException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()
            ->route('transfers.create', ['from' => $from->id])
            ->with('success', __(':qty :uom :sku transferred from :from to :to. Now :now at :to.', [
                'qty' => Format::qty($request->validated('qty')), 'uom' => $item->uom, 'sku' => $item->sku,
                'from' => $from->code, 'to' => $to->code, 'now' => Format::qty($in->qty_after),
            ]));
    }
}
