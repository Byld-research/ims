<?php

namespace App\Http\Controllers;

use App\Exceptions\PurchaseOrderException;
use App\Http\Requests\PurchaseOrderLineRequest;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Order lines, editable only while the order is a draft (SPEC 5.3.7).
 */
class PurchaseOrderLineController extends Controller
{
    public function store(PurchaseOrderLineRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $orders): RedirectResponse
    {
        try {
            $orders->addLine(
                $purchaseOrder,
                Item::query()->findOrFail($request->validated('item_id')),
                $request->validated('qty_ordered'),
                $request->validated('unit_price'),
            );
        } catch (PurchaseOrderException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', __('Line added.'));
    }

    public function update(PurchaseOrderLineRequest $request, PurchaseOrderLine $purchaseOrderLine, PurchaseOrderService $orders): RedirectResponse
    {
        try {
            $orders->updateLine($purchaseOrderLine, $request->validated('qty_ordered'), $request->validated('unit_price'));
        } catch (PurchaseOrderException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', __('Line updated.'));
    }

    public function destroy(PurchaseOrderLine $purchaseOrderLine, PurchaseOrderService $orders): RedirectResponse
    {
        Gate::authorize('update', $purchaseOrderLine->purchaseOrder);

        try {
            $orders->removeLine($purchaseOrderLine);
        } catch (PurchaseOrderException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', __('Line removed.'));
    }
}
