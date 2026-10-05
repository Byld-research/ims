<?php

namespace App\Http\Controllers;

use App\Exceptions\PurchaseOrderException;
use App\Exceptions\StockException;
use App\Http\Requests\ReceiveGoodsRequest;
use App\Models\PurchaseOrder;
use App\Models\SupplierItem;
use App\Services\PurchaseOrderService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Receive goods against an order, fully or partly (SPEC 5.3; screen 9).
 */
class ReceiptController extends Controller
{
    public function create(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        Gate::authorize('receive', $purchaseOrder);

        if (! $purchaseOrder->status->acceptsReceipts()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', __('This order is :status; goods cannot be received against it.', ['status' => strtolower($purchaseOrder->status->label())]));
        }

        $purchaseOrder->load(['supplier', 'site', 'lines' => fn ($q) => $q->with('item')->orderBy('id')]);

        return view('purchase-orders.receive', [
            'order' => $purchaseOrder,
            'lines' => $purchaseOrder->lines->reject->isFulfilled()->values(),
            'packSizes' => SupplierItem::query()
                ->where('supplier_id', $purchaseOrder->supplier_id)
                ->whereIn('item_id', $purchaseOrder->lines->pluck('item_id'))
                ->pluck('pack_size', 'item_id'),
        ]);
    }

    public function store(ReceiveGoodsRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $orders): RedirectResponse
    {
        try {
            $overReceived = $orders->receive($purchaseOrder, $request->quantities(), $request->user());
        } catch (PurchaseOrderException|StockException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        $redirect = redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', trans_choice('{1} Receipt recorded for 1 line.|[2,*] Receipt recorded for :count lines.', count($request->quantities())));

        if ($overReceived) {
            // Allowed, but flagged: over-receipt usually means a pack-size mix-up (SPEC 5.3.4).
            $redirect->with('warning', __('More than ordered was received for :lines. This usually means a pack-size mix-up: check whether packs were counted instead of units.', [
                'lines' => collect($overReceived)->map(fn ($o) => $o['line']->item->sku.' (+'.Format::qty($o['excess']).' '.$o['line']->item->uom.')')->join(', '),
            ]));
        }

        return $redirect;
    }
}
