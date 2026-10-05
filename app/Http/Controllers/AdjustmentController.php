<?php

namespace App\Http\Controllers;

use App\Enums\ReasonCodeScope;
use App\Exceptions\StockException;
use App\Http\Requests\AdjustmentRequest;
use App\Models\Item;
use App\Models\ReasonCode;
use App\Models\Site;
use App\Models\Stock;
use App\Services\StockService;
use App\Support\CurrentSite;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stock corrections and opening balances (SPEC 7, screen 11).
 */
class AdjustmentController extends Controller
{
    public function create(Request $request, CurrentSite $currentSite): View
    {
        $sites = Site::query()->active()->orderBy('code')->get()
            ->filter(fn (Site $site) => $request->user()->can('adjust', [Stock::class, $site]));

        abort_if($sites->isEmpty(), 403);

        // The header site, never a silent substitute; an explicit ?site= wins for admins (SPEC 7).
        $siteId = $request->integer('site') ?: $currentSite->id();

        return view('adjustments.create', [
            'sites' => $sites,
            'siteId' => $sites->firstWhere('id', $siteId)?->id,
            'item' => Item::query()->find($request->integer('item')),
            'reasons' => ReasonCode::query()->active()->for(ReasonCodeScope::Adjustment)->orderBy('label')->get(),
            'reasonId' => $request->integer('reason') ?: null,
            'direction' => $request->query('direction', 'in'),
        ]);
    }

    public function store(AdjustmentRequest $request, StockService $stock): RedirectResponse
    {
        $data = $request->validated();
        $item = Item::query()->findOrFail($data['item_id']);
        $site = $request->site();

        try {
            $transaction = $stock->adjust(
                item: $item,
                site: $site,
                increase: $data['direction'] === 'in',
                qty: $data['qty'],
                reason: ReasonCode::query()->findOrFail($data['reason_code_id']),
                user: $request->user(),
                unitCost: $data['unit_cost'] ?? null,
                note: $data['note'] ?? null,
            );
        } catch (StockException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }

        // Stay on the form with site, reason and direction kept: the opening count is entered item after item.
        return redirect()
            ->route('adjustments.create', ['site' => $site->id, 'reason' => $data['reason_code_id'], 'direction' => $data['direction']])
            ->with('success', __(':sku at :site: :delta :uom, now :qty.', [
                'sku' => $item->sku,
                'site' => $site->code,
                'delta' => ($data['direction'] === 'in' ? '+' : '−').Format::qty($data['qty']),
                'uom' => $item->uom,
                'qty' => Format::qty($transaction->qty_after),
            ]))
            ->with('lastItem', $item->id);
    }
}
