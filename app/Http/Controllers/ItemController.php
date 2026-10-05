<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\ItemRequest;
use App\Models\Category;
use App\Models\Item;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Support\CsvExport;
use App\Support\LedgerCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{
    public function show(Request $request, Item $item): Response
    {
        Gate::authorize('view', $item);

        $historySite = $request->integer('history_site') ?: null;

        if (CsvExport::requested($request)) {
            return LedgerCsv::download('movements-'.strtolower($item->sku),
                $item->transactions()->getQuery()->when($historySite, fn ($q, $siteId) => $q->where('site_id', $siteId)));
        }

        $item->load([
            'category.parent',
            'stocks',
            'supplierItems' => fn ($q) => $q->with('supplier')->orderByDesc('updated_at'),
            'machineTypes' => fn ($q) => $q->orderBy('code'),
        ]);

        return response()->view('items.show', [
            'item' => $item,
            'sites' => Site::query()->active()->orderBy('code')->get(),
            'stocksBySite' => $item->stocks->keyBy('site_id'),
            'historySite' => $historySite,
            'onOrder' => PurchaseOrderLine::onOrder([$item->id])[$item->id] ?? [],
            'openOrders' => PurchaseOrderLine::query()
                ->with(['purchaseOrder.supplier', 'purchaseOrder.site'])
                ->where('item_id', $item->id)
                ->where('is_closed', false)
                ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', array_filter(PurchaseOrderStatus::cases(), fn ($s) => $s->acceptsReceipts())))
                ->get()
                ->filter(fn (PurchaseOrderLine $line) => bccomp($line->outstanding(), '0', 3) > 0),
            'transactions' => $item->transactions()
                ->with(['site', 'counterSite', 'machine', 'reasonCode', 'user', 'purchaseOrderLine.purchaseOrder'])
                ->when($historySite, fn ($q, $siteId) => $q->where('site_id', $siteId))
                ->latest('id')
                ->paginate(25, pageName: 'history')
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Item::class);

        return view('items.form', [
            'item' => new Item(['uom' => 'pc', 'is_active' => true]),
            'categories' => Category::assignableOptions(),
            'skuLocked' => false,
        ]);
    }

    public function store(ItemRequest $request): RedirectResponse
    {
        $item = Item::query()->create($request->validated());

        return redirect()->route('items.show', $item)->with('success', __('Item :sku created.', ['sku' => $item->sku]));
    }

    public function edit(Item $item): View
    {
        Gate::authorize('update', $item);

        return view('items.form', [
            'item' => $item,
            'categories' => Category::assignableOptions(),
            'skuLocked' => $item->isSkuLocked(),
        ]);
    }

    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        $item->update($request->validated());

        return redirect()->route('items.show', $item)->with('success', __('Item updated.'));
    }
}
