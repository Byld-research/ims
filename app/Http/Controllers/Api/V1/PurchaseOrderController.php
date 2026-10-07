<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PurchaseOrderStatus;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Purchase orders with their lines (SPEC 7a).
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', ...array_column(PurchaseOrderStatus::cases(), 'value')])],
            'supplier' => ['nullable', 'integer'],
            'updated_since' => ['nullable', 'date'],
        ]);
        $siteId = $this->siteId($request);

        return PurchaseOrderResource::collection(PurchaseOrder::query()
            ->with(['supplier', 'site', 'lines.item'])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $status === 'open'
                ? $q->whereNotIn('status', [PurchaseOrderStatus::Closed, PurchaseOrderStatus::Cancelled])
                : $q->where('status', $status))
            ->when($filters['supplier'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['updated_since'] ?? null, fn ($q, $since) => $q->where('updated_at', '>=', now()->parse($since)->utc()))
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString());
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->ensureInScope($request, $purchaseOrder->site_id);

        return new PurchaseOrderResource($purchaseOrder->load(['supplier', 'site', 'lines.item']));
    }
}
