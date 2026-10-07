<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Http\Resources\StockMovementResource;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The movement history, oldest first (SPEC 7a). after_id gives an incremental feed: store the
 * last id received and ask for the movements after it. Movements are never changed, so a
 * movement once received stays valid.
 */
class StockMovementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'item' => ['nullable', 'integer'],
            'sku' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'machine' => ['nullable', 'string', 'max:10'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $siteId = $this->siteId($request);

        return StockMovementResource::collection(StockTransaction::query()
            ->with(['item', 'site', 'machine', 'counterSite', 'purchaseOrderLine.purchaseOrder', 'stockCount', 'reasonCode', 'user'])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($filters['after_id'] ?? null, fn ($q, $id) => $q->where('id', '>', $id))
            ->when($filters['item'] ?? null, fn ($q, $id) => $q->where('item_id', $id))
            ->when($filters['sku'] ?? null, fn ($q, $sku) => $q->whereHas('item', fn ($q) => $q->where('sku', $sku)))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['machine'] ?? null, fn ($q, $sku) => $q->whereHas('machine', fn ($q) => $q->where('sku', $sku)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', now()->parse($from)->utc()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', now()->parse($to)->utc()->addDay()))
            ->orderBy('id')
            ->paginate($this->perPage($request))
            ->withQueryString());
    }
}
