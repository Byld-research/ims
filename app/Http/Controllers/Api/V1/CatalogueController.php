<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Criticality;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\SiteResource;
use App\Http\Resources\StockResource;
use App\Http\Resources\SupplierResource;
use App\Models\Category;
use App\Models\Item;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Sites, categories, items and suppliers (SPEC 7a). Shared master data: not limited by site,
 * except the stock embedded in an item.
 */
class CatalogueController extends Controller
{
    public function sites(Request $request): AnonymousResourceCollection
    {
        return SiteResource::collection(
            $this->client($request)->limitToSite(Site::query(), 'id')->orderBy('code')->get()
        );
    }

    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->with('parent')->orderBy('parent_id')->orderBy('name')->get());
    }

    public function items(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'integer'],
            'criticality' => ['nullable', Rule::enum(Criticality::class)],
            'active' => ['nullable', 'boolean'],
            'updated_since' => ['nullable', 'date'],
        ]);
        $siteId = $this->siteId($request);

        $items = Item::query()
            ->with(['category.parent', 'stocks' => fn ($q) => $q->with('site')->when($siteId, fn ($q) => $q->where('site_id', $siteId))])
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('name', 'like', $like)->orWhere('mpn', 'like', $like));
            })
            ->when($filters['sku'] ?? null, fn ($q, $sku) => $q->where('sku', $sku))
            ->when($filters['category'] ?? null, function ($q, $id) {
                $category = Category::query()->find($id);
                $q->whereIn('category_id', $category ? $category->selfAndChildIds() : [0]);
            })
            ->when($filters['criticality'] ?? null, fn ($q, $c) => $q->where('criticality', $c))
            ->when(array_key_exists('active', $filters), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($filters['updated_since'] ?? null, fn ($q, $since) => $q->where('updated_at', '>=', now()->parse($since)->utc()))
            ->orderBy('sku')
            ->paginate($this->perPage($request))
            ->withQueryString();

        StockResource::$onOrder = PurchaseOrderLine::onOrder($items->pluck('id')->all(), $siteId);

        return ItemResource::collection($items);
    }

    public function item(Request $request, Item $item): ItemResource
    {
        $siteId = $this->siteId($request);

        $item->load(['category.parent', 'supplierItems.supplier',
            'stocks' => fn ($q) => $q->with('site')->when($siteId, fn ($q) => $q->where('site_id', $siteId))]);
        StockResource::$onOrder = PurchaseOrderLine::onOrder([$item->id], $siteId);

        return new ItemResource($item);
    }

    public function suppliers(Request $request): AnonymousResourceCollection
    {
        $request->validate(['active' => ['nullable', 'boolean']]);

        return SupplierResource::collection(Supplier::query()
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString());
    }

    public function supplier(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier->load('supplierItems.item'));
    }
}
