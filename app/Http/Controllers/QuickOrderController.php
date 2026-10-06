<?php

namespace App\Http\Controllers;

use App\Exceptions\PurchaseOrderException;
use App\Http\Requests\QuickOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Services\QuickOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Quick order: items ticked on the dashboard become draft purchase orders (SPEC 5.3a).
 */
class QuickOrderController extends Controller
{
    public function create(Request $request, QuickOrder $quickOrder): View|RedirectResponse
    {
        $ids = $request->validate(['stocks' => ['nullable', 'array'], 'stocks.*' => ['integer']])['stocks'] ?? [];
        $stocks = $this->writableStocks($request, $ids);

        if ($stocks->isEmpty()) {
            return redirect()->route('dashboard')->with('warning', __('Tick at least one item under Stock to act on, then choose Order selected.'));
        }

        return view('purchase-orders.quick', [
            'rows' => $quickOrder->suggest($stocks),
            'suppliers' => Supplier::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'siteCodes' => $stocks->pluck('site_id')->unique()->count() > 1,
        ]);
    }

    public function store(QuickOrderRequest $request, QuickOrder $quickOrder): RedirectResponse
    {
        $ticked = $request->ticked();

        if ($ticked === []) {
            return back()->withInput()->with('warning', __('Tick at least one item to order.'));
        }

        $stocks = $this->writableStocks($request, array_keys($ticked))->keyBy('id');
        abort_if($stocks->count() !== count($ticked), 403);

        $suppliers = Supplier::query()->whereKey(array_column($ticked, 'supplier_id'))->get()->keyBy('id');
        $lastPrices = SupplierItem::query()
            ->whereIn('item_id', $stocks->pluck('item_id'))
            ->whereNotNull('last_price')
            ->get()
            ->keyBy(fn (SupplierItem $l) => $l->supplier_id.':'.$l->item_id);

        $errors = [];
        $lines = [];
        foreach ($ticked as $stockId => $line) {
            $stock = $stocks->get($stockId);
            if ($line['unit_price'] === null && ! $lastPrices->has($line['supplier_id'].':'.$stock->item_id)) {
                $errors["lines.{$stockId}.unit_price"] = __('Enter a price: :supplier has no last price for :sku.', [
                    'supplier' => $suppliers->get($line['supplier_id'])->name, 'sku' => $stock->item->sku,
                ]);
            }
            $lines[] = ['stock' => $stock, 'supplier' => $suppliers->get($line['supplier_id']), 'qty' => $line['qty'], 'unit_price' => $line['unit_price']];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        try {
            $orders = $quickOrder->place($lines, $request->user());
        } catch (PurchaseOrderException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if (count($orders) === 1) {
            return redirect()->route('purchase-orders.show', $orders[0])
                ->with('success', __('Draft :number created. Check it, then send it to the supplier.', ['number' => $orders[0]->number]));
        }

        return redirect()->route('purchase-orders.index', ['status' => 'DRAFT'])
            ->with('success', __(':n drafts created: :numbers. Check each one, then send it to the supplier.', [
                'n' => count($orders),
                'numbers' => implode(', ', array_map(fn (PurchaseOrder $o) => $o->number, $orders)),
            ]));
    }

    /**
     * Stock rows of active items at sites the user may order for, in the order given.
     *
     * @param  list<int|string>  $ids
     * @return Collection<int, Stock>
     */
    private function writableStocks(Request $request, array $ids): Collection
    {
        $stocks = Stock::query()
            ->with(['item', 'site'])
            ->whereKey($ids)
            ->whereHas('item', fn ($q) => $q->active())
            ->get()
            ->filter(fn (Stock $stock) => $request->user()->can('create', [PurchaseOrder::class, $stock->site]))
            ->keyBy('id');

        return collect($ids)->map(fn ($id) => $stocks->get((int) $id))->filter()->unique('id')->values();
    }
}
