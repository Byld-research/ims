<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Site;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Current stock of one item at one site, so forms can show the resulting quantity (SPEC 7, principle 4).
 */
class StockLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Stock::class);

        $validated = $request->validate([
            'item' => ['required', 'integer'],
            'site' => ['required', 'integer'],
        ]);

        $item = Item::query()->findOrFail($validated['item']);
        $site = Site::query()->findOrFail($validated['site']);
        $stock = Stock::query()->where('item_id', $item->id)->where('site_id', $site->id)->first();

        return response()->json([
            'item' => ['id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'uom' => $item->uom],
            'site' => $site->code,
            'qty' => $stock?->qty ?? '0.000',
            'avg_cost' => $stock?->avg_cost ?? '0.0000',
            'has_cost' => $stock !== null && (bccomp($stock->qty, '0', 3) > 0 || bccomp($stock->avg_cost, '0', 4) > 0),
            'bin' => $stock?->bin,
        ]);
    }
}
