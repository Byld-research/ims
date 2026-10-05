<?php

namespace App\Http\Controllers;

use App\Enums\Criticality;
use App\Models\Category;
use App\Models\Item;
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
        ]);

        $sites = Site::query()->active()->orderBy('code')->get();
        $query = $this->query($filters);

        if (CsvExport::requested($request)) {
            return $this->export($query, $sites);
        }

        return response()->view('stock.index', [
            'items' => $query->paginate(50)->withQueryString(),
            'sites' => $sites,
            'currentSiteId' => $currentSite->id(),
            'filters' => $filters,
            'categories' => Category::assignableOptions(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters): Builder
    {
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
            ->orderBy('sku');
    }

    private function export(Builder $query, $sites): Response
    {
        $headings = ['SKU', 'Name', 'Category', 'UoM', 'Criticality', 'Manufacturer', 'MPN', 'Active'];
        foreach ($sites as $site) {
            $headings[] = $site->code.' qty';
        }

        $rows = (function () use ($query, $sites) {
            foreach ($query->lazy(500) as $item) {
                $stocks = $item->stocks->keyBy('site_id');
                $row = [$item->sku, $item->name, $item->category->fullName(), $item->uom, $item->criticality,
                    $item->manufacturer, $item->mpn, $item->is_active];
                foreach ($sites as $site) {
                    $row[] = $stocks->get($site->id)?->qty ?? '0.000';
                }
                yield $row;
            }
        })();

        return CsvExport::download('stock', $headings, $rows);
    }
}
