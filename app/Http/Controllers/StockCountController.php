<?php

namespace App\Http\Controllers;

use App\Enums\StockCountStatus;
use App\Exceptions\StockCountException;
use App\Http\Requests\StockCountEntriesRequest;
use App\Http\Requests\StockCountLinesRequest;
use App\Http\Requests\StockCountPostRequest;
use App\Http\Requests\StockCountRequest;
use App\Models\Category;
use App\Models\Item;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockTransaction;
use App\Services\StockCountService;
use App\Support\CsvExport;
use App\Support\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cycle counts (SPEC 5.6; screen 12).
 */
class StockCountController extends Controller
{
    public function __construct(private readonly StockCountService $counts) {}

    public function index(Request $request, CurrentSite $currentSite): Response
    {
        Gate::authorize('viewAny', StockCount::class);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(StockCountStatus::class)]]);
        $site = $currentSite->get();

        $query = StockCount::query()
            ->with(['site', 'creator', 'poster'])
            ->withCount(['lines', 'lines as counted_count' => fn ($q) => $q->whereNotNull('qty_counted')])
            ->when($site, fn ($q) => $q->where('site_id', $site->id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id');

        if (CsvExport::requested($request)) {
            return CsvExport::download('stock-counts', ['Reference', 'Site', 'Status', 'Scope', 'Lines', 'Counted', 'Created', 'Created by', 'Posted', 'Posted by'],
                $query->lazy()->map(fn (StockCount $c) => [$c->reference, $c->site->code, $c->status, $c->scope_note, $c->lines_count,
                    $c->counted_count, $c->created_at, $c->creator->name, $c->posted_at, $c->poster?->name]));
        }

        return response()->view('stock-counts.index', [
            'counts' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
            'site' => $site,
            // Suggested frequencies, surfaced rather than enforced (SPEC 5.6).
            'dueCount' => $site ? Stock::query()->where('stocks.site_id', $site->id)->dueForCount()->count() : null,
        ]);
    }

    public function create(Request $request, CurrentSite $currentSite): View
    {
        $sites = Site::query()->active()->orderBy('code')->get()
            ->filter(fn (Site $site) => $request->user()->can('create', [StockCount::class, $site]));

        abort_if($sites->isEmpty(), 403);

        return view('stock-counts.create', [
            'sites' => $sites,
            'siteId' => $sites->firstWhere('id', $currentSite->id())?->id,
        ]);
    }

    public function store(StockCountRequest $request): RedirectResponse
    {
        $count = $this->counts->create(Site::query()->findOrFail($request->validated('site_id')), $request->user(), $request->validated('scope_note'));

        return redirect()->route('stock-counts.show', $count)->with('success', __('Count :ref created. Add the items to count.', ['ref' => $count->reference]));
    }

    public function show(Request $request, StockCount $stockCount): Response
    {
        Gate::authorize('view', $stockCount);

        $stockCount->load(['site', 'creator', 'poster']);

        // Walk order: by bin, then SKU, so the counter moves shelf by shelf.
        $lines = $stockCount->lines()->with('item')->get();
        $stocks = Stock::query()->where('site_id', $stockCount->site_id)->whereIn('item_id', $lines->pluck('item_id'))->get()->keyBy('item_id');
        $lines = $lines->sortBy(fn (StockCountLine $l) => [($stocks->get($l->item_id)?->bin ?? "\u{FFFF}"), $l->item->sku])->values();

        if (CsvExport::requested($request)) {
            return CsvExport::download('count-sheet-'.strtolower($stockCount->reference), ['Reference', 'Location', 'SKU', 'Name', 'UoM', 'Counted', 'Note'],
                $lines->map(fn (StockCountLine $l) => [$stockCount->reference, $stocks->get($l->item_id)?->bin, $l->item->sku, $l->item->name,
                    $l->item->uom, $l->qty_counted, $l->note]));
        }

        return response()->view('stock-counts.show', [
            'count' => $stockCount,
            'lines' => $lines,
            'stocks' => $stocks,
            'categories' => Category::query()->whereNull('parent_id')->with('children')->orderBy('name')->get(),
            'dueCount' => Stock::query()->where('stocks.site_id', $stockCount->site_id)->dueForCount()->count(),
            'adjustments' => $stockCount->status === StockCountStatus::Posted
                ? StockTransaction::query()->where('stock_count_id', $stockCount->id)
                    ->with(['item', 'site', 'user', 'reasonCode', 'machine', 'counterSite', 'purchaseOrderLine.purchaseOrder'])
                    ->latest('id')->paginate(100, pageName: 'history')
                : null,
        ]);
    }

    public function addLines(StockCountLinesRequest $request, StockCount $stockCount): RedirectResponse
    {
        $data = $request->validated();
        $site = $stockCount->site_id;

        $itemIds = match ($data['by']) {
            'item' => [$data['item_id']],
            'category' => Item::query()->active()->whereIn('category_id', Category::query()->findOrFail($data['category_id'])->selfAndChildIds())->pluck('id')->all(),
            'criticality' => Item::query()->active()->where('criticality', $data['criticality'])->pluck('id')->all(),
            'kanban' => Stock::query()->where('site_id', $site)->where('is_kanban', true)->pluck('item_id')->all(),
            'due' => Stock::query()->where('stocks.site_id', $site)->dueForCount()->pluck('stocks.item_id')->all(),
        };

        try {
            $added = $this->counts->addItems($stockCount, $itemIds);
        } catch (StockCountException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', trans_choice('{0} No new items: they are all on the count already.|{1} 1 item added.|[2,*] :count items added.', $added, ['count' => $added]));
    }

    public function removeLine(StockCountLine $stockCountLine): RedirectResponse
    {
        Gate::authorize('update', $stockCountLine->stockCount);

        return $this->run(fn () => $this->counts->removeLine($stockCountLine), __('Item removed from the count.'));
    }

    public function start(StockCount $stockCount): RedirectResponse
    {
        Gate::authorize('update', $stockCount);

        return $this->run(fn () => $this->counts->startCounting($stockCount), __('Counting started. Enter what you find on the shelves.'));
    }

    public function saveCounts(StockCountEntriesRequest $request, StockCount $stockCount): RedirectResponse
    {
        $result = $this->run(fn () => $this->counts->recordCounts($stockCount, $request->entries()), __('Counts saved.'));

        return $request->boolean('review') && ! session()->has('errors')
            ? redirect()->route('stock-counts.review', $stockCount)
            : $result;
    }

    public function review(StockCount $stockCount): View|RedirectResponse
    {
        Gate::authorize('post', $stockCount);

        if ($stockCount->status !== StockCountStatus::Counting) {
            return redirect()->route('stock-counts.show', $stockCount);
        }

        $preview = $this->counts->preview($stockCount->load('site'));

        return view('stock-counts.review', [
            'count' => $stockCount,
            'preview' => $preview,
            'counted' => $preview->whereNotNull('difference'),
            'skipped' => $preview->whereNull('difference')->count(),
            'adjusting' => $preview->filter(fn ($row) => $row['difference'] !== null && bccomp($row['difference'], '0', 3) !== 0),
            'moved' => $preview->where('moved', true)->count(),
        ]);
    }

    public function post(StockCountPostRequest $request, StockCount $stockCount): RedirectResponse
    {
        try {
            $result = $this->counts->post($stockCount, $request->user(), $request->costs());
        } catch (StockCountException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('stock-counts.show', $stockCount)->with('success', __('Count posted: :adjusted adjusted, :unchanged confirmed unchanged, :skipped not counted.', $result));
    }

    public function cancel(StockCount $stockCount): RedirectResponse
    {
        Gate::authorize('update', $stockCount);

        return $this->run(fn () => $this->counts->cancel($stockCount), __('Count cancelled. Nothing was changed.'));
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (StockCountException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
