<?php

namespace App\Http\Controllers;

use App\Exceptions\PurchaseOrderException;
use App\Http\Requests\PurchaseOrderActionRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Status changes on an order (SPEC 5.3, process steps 4–9).
 */
class PurchaseOrderActionController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $orders) {}

    public function order(PurchaseOrderActionRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->run(fn () => $this->orders->markOrdered($purchaseOrder), __('Marked as sent to the supplier.'));
    }

    public function confirm(PurchaseOrderActionRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->run(fn () => $this->orders->confirm($purchaseOrder, Carbon::parse($request->validated('eta'))),
            __('Confirmation recorded.'));
    }

    public function ship(PurchaseOrderActionRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->run(fn () => $this->orders->ship($purchaseOrder, $request->validated('tracking_ref')),
            __('Shipment recorded.'));
    }

    public function cancel(PurchaseOrderActionRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->run(fn () => $this->orders->cancel($purchaseOrder, $request->user(), $request->validated('reason')),
            __('Order cancelled.'));
    }

    public function close(PurchaseOrderActionRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->run(fn () => $this->orders->close($purchaseOrder), __('Order closed.'));
    }

    public function closeShort(PurchaseOrderActionRequest $request, PurchaseOrderLine $purchaseOrderLine): RedirectResponse
    {
        return $this->run(fn () => $this->orders->closeLineShort($purchaseOrderLine, $request->user(), $request->validated('reason')),
            __('Line closed short.'));
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (PurchaseOrderException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
