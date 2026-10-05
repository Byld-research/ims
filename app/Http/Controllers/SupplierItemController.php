<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierItemRequest;
use App\Models\Supplier;
use App\Models\SupplierItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Links between suppliers and items. A link is reference data with no history of its own,
 * so removing one is allowed; past orders keep their own prices.
 */
class SupplierItemController extends Controller
{
    public function store(SupplierItemRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->supplierItems()->create($request->validated());

        return back()->with('success', __('Item linked.'));
    }

    public function update(SupplierItemRequest $request, SupplierItem $supplierItem): RedirectResponse
    {
        $supplierItem->update($request->validated());

        return back()->with('success', __('Link updated.'));
    }

    public function destroy(SupplierItem $supplierItem): RedirectResponse
    {
        Gate::authorize('update', $supplierItem->supplier);

        $supplierItem->delete();

        return back()->with('success', __('Item unlinked.'));
    }
}
