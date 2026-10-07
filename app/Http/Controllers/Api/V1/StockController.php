<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\StockResource;
use App\Models\PurchaseOrderLine;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Stock per item and site (SPEC 7a): quantity, levels, location, value, status, on order.
 */
class StockController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'item' => ['nullable', 'integer'],
            'sku' => ['nullable', 'string', 'max:40'],
            'needs_replenishment' => ['nullable', 'boolean'],
            'two_bin' => ['nullable', 'boolean'],
        ]);
        $siteId = $this->siteId($request);

        $stocks = Stock::query()
            ->select('stocks.*')
            ->join('items', 'items.id', '=', 'stocks.item_id')
            ->with(['item', 'site'])
            ->when($siteId, fn ($q) => $q->where('stocks.site_id', $siteId))
            ->when($request->integer('item'), fn ($q, $id) => $q->where('stocks.item_id', $id))
            ->when($request->input('sku'), fn ($q, $sku) => $q->where('items.sku', $sku))
            ->when($request->boolean('needs_replenishment'), fn ($q) => $q->needsReplenishment())
            ->when($request->has('two_bin'), fn ($q) => $q->where('stocks.is_kanban', $request->boolean('two_bin')))
            ->orderBy('items.sku')
            ->orderBy('stocks.site_id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        StockResource::$onOrder = PurchaseOrderLine::onOrder($stocks->pluck('item_id')->unique()->values()->all(), $siteId);

        return StockResource::collection($stocks);
    }
}
