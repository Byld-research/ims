<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockLevelRequest;
use App\Models\Category;
use App\Models\Item;
use App\Models\Site;
use App\Models\Stock;
use App\Support\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Bulk editor for min level, bin and kanban settings at one site (SPEC 7, screen 6).
 * These are settings, not movements: quantity and cost are never touched here.
 */
class StockLevelController extends Controller
{
    public function edit(Request $request, CurrentSite $currentSite): View
    {
        $filters = $request->validate([
            'site' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'kanban' => ['nullable', 'boolean'],
            'unset' => ['nullable', 'boolean'],
        ]);

        $site = Site::query()->active()->find($filters['site'] ?? $currentSite->id());

        if (! $site) {
            Gate::authorize('setLevels', [Stock::class, $request->user()->site ?? Site::query()->active()->firstOrFail()]);

            return view('stock.levels-choose-site', ['sites' => Site::query()->active()->orderBy('code')->get()]);
        }

        Gate::authorize('setLevels', [Stock::class, $site]);

        $items = Item::query()
            ->active()
            ->with(['category', 'stocks' => fn ($q) => $q->where('site_id', $site->id)])
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->whereIn('category_id', Category::query()->find($id)?->selfAndChildIds() ?? [0]))
            ->when($filters['kanban'] ?? false, fn ($q) => $q->whereHas('stocks', fn ($q) => $q->where('site_id', $site->id)->where('is_kanban', true)))
            ->when($filters['unset'] ?? false, fn ($q) => $q->whereDoesntHave('stocks', fn ($q) => $q
                ->where('site_id', $site->id)->where(fn ($q) => $q->where('min_level', '>', 0)->orWhere('is_kanban', true))))
            ->orderBy('sku')
            ->paginate(50)
            ->withQueryString();

        return view('stock.levels', [
            'site' => $site,
            'items' => $items,
            'filters' => $filters,
            'categories' => Category::assignableOptions(),
        ]);
    }

    public function update(StockLevelRequest $request): RedirectResponse
    {
        $site = $request->site();
        $changed = 0;

        DB::transaction(function () use ($request, $site, &$changed) {
            foreach ($request->validated('rows') as $itemId => $row) {
                $stock = Stock::query()->firstOrNew(['item_id' => $itemId, 'site_id' => $site->id]);

                $stock->fill([
                    'min_level' => $row['min_level'] ?? '0',
                    'bin' => $row['bin'],
                    'is_kanban' => $row['is_kanban'],
                    'bin_qty' => $row['bin_qty'],
                ]);

                // Untouched rows for items never stocked here stay absent.
                $isEmpty = ! $stock->exists && bccomp($stock->min_level, '0', 3) === 0
                    && $stock->bin === null && ! $stock->is_kanban && $stock->bin_qty === null;

                if ($stock->isDirty() && ! $isEmpty) {
                    $stock->save();
                    $changed++;
                }
            }
        });

        return back()->with('success', trans_choice('{0} Nothing changed.|{1} 1 item updated at :site.|[2,*] :count items updated at :site.', $changed, [
            'count' => $changed, 'site' => $site->code,
        ]));
    }
}
