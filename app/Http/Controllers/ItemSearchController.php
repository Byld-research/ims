<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * JSON source for the item picker: active items matching SKU, name or MPN.
 */
class ItemSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Item::class);

        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $items = Item::query()
            ->active()
            ->where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('name', 'like', $like)->orWhere('mpn', 'like', $like))
            ->orderByRaw('sku = ? desc, sku like ? desc', [$term, addcslashes($term, '%_\\').'%'])
            ->orderBy('sku')
            ->limit(20)
            ->get(['id', 'sku', 'name', 'uom']);

        return response()->json($items);
    }
}
