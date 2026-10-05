<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Enums\TransactionType;
use App\Http\Requests\PurchaseOrderDetailsRequest;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Services\PurchaseOrderService;
use App\Support\CsvExport;
use App\Support\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Purchase orders (SPEC 5.3; screens 7 and 8).
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request, CurrentSite $currentSite): Response
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', ...array_column(PurchaseOrderStatus::cases(), 'value')])],
            'supplier' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:40'],
        ]);

        $query = PurchaseOrder::query()
            ->with(['supplier', 'site'])
            ->withCount('lines')
            ->addSelect(['total' => PurchaseOrderLine::query()
                ->selectRaw('COALESCE(SUM(qty_ordered * unit_price), 0)')
                ->whereColumn('purchase_order_id', 'purchase_orders.id')])
            ->when($currentSite->id(), fn ($q, $siteId) => $q->where('site_id', $siteId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $status === 'open'
                ? $q->whereNotIn('status', [PurchaseOrderStatus::Closed, PurchaseOrderStatus::Cancelled])
                : $q->where('status', $status))
            ->when($filters['supplier'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('number', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('tracking_ref', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->latest('id');

        if (CsvExport::requested($request)) {
            return CsvExport::download('purchase-orders',
                ['Number', 'Site', 'Supplier', 'Status', 'Ordered', 'Confirmed', 'ETA', 'Shipped', 'Tracking', 'Closed', 'Lines', 'Value (USD)'],
                $query->lazy()->map(fn (PurchaseOrder $o) => [$o->number, $o->site->code, $o->supplier->name, $o->status,
                    $o->ordered_at?->toDateString(), $o->confirmed_at?->toDateString(), $o->eta?->toDateString(),
                    $o->shipped_at?->toDateString(), $o->tracking_ref, $o->closed_at?->toDateString(), $o->lines_count, $o->total]));
        }

        return response()->view('purchase-orders.index', [
            'orders' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
            'site' => $currentSite->get(),
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id')->all(),
            'statuses' => ['open' => __('Open (not closed or cancelled)')]
                + collect(PurchaseOrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
        ]);
    }

    public function create(Request $request, CurrentSite $currentSite): View
    {
        $sites = Site::query()->active()->orderBy('code')->get()
            ->filter(fn (Site $site) => $request->user()->can('create', [PurchaseOrder::class, $site]));

        abort_if($sites->isEmpty(), 403);

        return view('purchase-orders.create', [
            'sites' => $sites,
            'siteId' => $sites->firstWhere('id', $currentSite->id())?->id,
            'suppliers' => Supplier::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'supplierId' => $request->integer('supplier') ?: null,
        ]);
    }

    public function store(PurchaseOrderRequest $request, PurchaseOrderService $orders): RedirectResponse
    {
        $order = $orders->create(
            Supplier::query()->findOrFail($request->validated('supplier_id')),
            Site::query()->findOrFail($request->validated('site_id')),
            $request->user(),
            $request->validated('notes'),
        );

        return redirect()->route('purchase-orders.show', $order)->with('success', __('Draft :number created. Add the lines, then send it.', ['number' => $order->number]));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'site', 'creator', 'lines' => fn ($q) => $q->with('item')->orderBy('id')]);

        $packSizes = SupplierItem::query()
            ->where('supplier_id', $purchaseOrder->supplier_id)
            ->whereIn('item_id', $purchaseOrder->lines->pluck('item_id'))
            ->pluck('pack_size', 'item_id');

        $receipts = StockTransaction::query()
            ->with(['item', 'site', 'user', 'purchaseOrderLine.purchaseOrder'])
            ->where('type', TransactionType::Receipt)
            ->whereIn('purchase_order_line_id', $purchaseOrder->lines->pluck('id'))
            ->latest('id')
            ->paginate(50, pageName: 'history');

        return view('purchase-orders.show', [
            'order' => $purchaseOrder,
            'packSizes' => $packSizes,
            'receipts' => $receipts,
            'total' => $purchaseOrder->lines->reduce(fn ($sum, $line) => bcadd($sum, $line->value(), 4), '0'),
        ]);
    }

    public function update(PurchaseOrderDetailsRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->update($request->validated());

        return back()->with('success', __('Order details saved.'));
    }
}
