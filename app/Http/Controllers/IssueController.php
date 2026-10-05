<?php

namespace App\Http\Controllers;

use App\Enums\ReasonCodeScope;
use App\Exceptions\StockException;
use App\Http\Requests\IssueRequest;
use App\Models\Item;
use App\Models\Machine;
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
 * Issue stock: item, destination, quantity (SPEC 7, principle 1; screen 10).
 */
class IssueController extends Controller
{
    public function create(Request $request, CurrentSite $currentSite): View
    {
        $sites = Site::query()->active()->orderBy('code')->get()
            ->filter(fn (Site $site) => $request->user()->can('issue', [Stock::class, $site]));

        abort_if($sites->isEmpty(), 403);

        $machine = Machine::query()->active()->find($request->integer('machine'));
        // A machine link decides the site; otherwise the header site, never a silent substitute (SPEC 7).
        $siteId = $machine?->site_id ?? $currentSite->id();

        return view('issues.create', [
            'sites' => $sites,
            'siteId' => $sites->firstWhere('id', $siteId)?->id,
            'machines' => Machine::query()->active()->whereIn('site_id', $sites->pluck('id'))->with('machineType')->orderBy('sku')->get()
                ->map(fn (Machine $m) => ['id' => $m->id, 'site_id' => $m->site_id, 'label' => $m->sku.' · '.$m->displayName()])->values(),
            'reasons' => ReasonCode::query()->active()->for(ReasonCodeScope::IssueGeneral)->orderBy('label')->get(),
            'item' => Item::query()->find($request->integer('item')),
            'machineId' => $machine?->id,
            'mode' => $request->query('mode') === 'general' ? 'general' : 'machine',
            'reasonId' => $request->integer('reason') ?: null,
        ]);
    }

    public function store(IssueRequest $request, StockService $stock): RedirectResponse
    {
        $item = Item::query()->findOrFail($request->validated('item_id'));
        $machine = $request->machine();
        $note = $request->validated('note');

        try {
            $transaction = $machine
                ? $stock->issueToMachine($item, $machine, $request->validated('qty'), $request->user(), $note)
                : $stock->issueGeneral($item, $request->site(), $request->validated('qty'),
                    ReasonCode::query()->findOrFail($request->validated('reason_code_id')), $request->user(), $note);
        } catch (StockException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }

        // Keep the destination: the next issue is often to the same machine.
        return redirect()
            ->route('issues.create', array_filter([
                'machine' => $machine?->id,
                'mode' => $machine ? null : 'general',
                'reason' => $machine ? null : $request->validated('reason_code_id'),
            ]))
            ->with('success', __(':qty :uom :sku issued to :destination. :left left at :site.', [
                'qty' => Format::qty($request->validated('qty')),
                'uom' => $item->uom,
                'sku' => $item->sku,
                'destination' => $machine ? $machine->sku : $transaction->reasonCode->label,
                'left' => Format::qty($transaction->qty_after),
                'site' => $transaction->site->code,
            ]));
    }
}
