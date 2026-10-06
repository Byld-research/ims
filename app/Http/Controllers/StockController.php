<?php

namespace App\Http\Controllers;

use App\Enums\Criticality;
use App\Models\Category;
use App\Models\Item;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Models\Stock;
use App\Support\CsvExport;
use App\Support\CurrentSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stock list (SPEC 7, screen 3): the catalogue with quantities at every site.
 */
class StockController extends Controller
{
    public function index(Request $request, CurrentSite $currentSite): Response
    {
        Gate::authorize('viewAny', Stock::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'criticality' => ['nullable', Rule::enum(Criticality::class)],
            'inactive' => ['nullable', 'boolean'],
            'below' => ['nullable', 'boolean'],
            'kanban' => ['nullable', 'boolean'],
        ]);

        $sites = Site::query()->active()->orderBy('code')->get();
        $query = $this->query($filters, $currentSite->id());

        if (CsvExport::requested($request)) {
            return $this->export($query, $sites, $currentSite->get());
        }

        $items = $query->paginate(50)->withQueryString();

        return response()->view('stock.index', [
            'items' => $items,
            // Shown next to shortages, never added to stock (SPEC 5.5).
            'onOrder' => PurchaseOrderLine::onOrder($items->pluck('id')->all(), $currentSite->id()),
            'sites' => $sites,
            'site' => $currentSite->get(),
            'currentSiteId' => $currentSite->id(),
            'filters' => $filters,
            'categories' => Category::assignableOptions(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  int|null  $siteId  the selected site; null filters across all sites
     */
    private function query(array $filters, ?int $siteId): Builder
    {
        $atSite = fn ($q) => $q->when($siteId, fn ($q) => $q->where('site_id', $siteId));

        return Item::query()
            ->with(['category.parent', 'stocks'])
            ->unless($filters['inactive'] ?? false, fn ($q) => $q->active())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('name', 'like', $like)->orWhere('mpn', 'like', $like));
            })
            ->when($filters['category'] ?? null, function ($q, $categoryId) {
                $category = Category::query()->find($categoryId);
                $q->whereIn('category_id', $category ? $category->selfAndChildIds() : [0]);
            })
            ->when($filters['criticality'] ?? null, fn ($q, $c) => $q->where('criticality', $c))
            ->when($filters['below'] ?? false, fn ($q) => $q->whereHas('stocks', fn ($q) => $atSite($q)->needsReplenishment()))
            ->when($filters['kanban'] ?? false, fn ($q) => $q->whereHas('stocks', fn ($q) => $atSite($q)->where('is_kanban', true)))
            ->when($filters['below'] ?? false,
                // High criticality first, then normal, low and unset (SPEC 8).
                fn ($q) => $q->orderByRaw(Criticality::orderSql('criticality')))
            ->orderBy('sku');
    }

    private function export(Builder $query, $sites, ?Site $site): Response
    {
        $headings = ['SKU', 'Name', 'Category', 'UoM', 'Criticality', 'Manufacturer', 'MPN', 'Active'];
        foreach ($sites as $each) {
            $headings[] = $each->code.' qty';
        }
        if ($site) {
            array_push($headings, $site->code.' min level', $site->code.' location', $site->code.' two-bin',
                $site->code.' qty per bin', $site->code.' avg cost', $site->code.' value', $site->code.' needs replenishment');
        }

        $rows = (function () use ($query, $sites, $site) {
            foreach ($query->lazy(500) as $item) {
                $stocks = $item->stocks->keyBy('site_id');
                $row = [$item->sku, $item->name, $item->category->fullName(), $item->uom, $item->criticality?->label(),
                    $item->manufacturer, $item->mpn, $item->is_active];
                foreach ($sites as $each) {
                    $row[] = $stocks->get($each->id)?->qty ?? '0.000';
                }
                if ($site) {
                    $stock = $stocks->get($site->id);
                    array_push($row, $stock?->min_level ?? '0.000', $stock?->bin, (bool) $stock?->is_kanban, $stock?->bin_qty,
                        $stock?->avg_cost ?? '0.0000', $stock?->value() ?? '0.0000', (bool) $stock?->needsReplenishment());
                }
                yield $row;
            }
        })();

        return CsvExport::download('stock', $headings, $rows);
    }
}
