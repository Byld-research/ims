<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Site;
use App\Services\Dashboard;
use App\Support\CurrentSite;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Alerts first, then the items that are moving, then the figures (SPEC 8).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentSite $currentSite): View
    {
        $dashboard = new Dashboard($currentSite->get());
        $alerts = $dashboard->alerts();
        $attention = $dashboard->attention();
        $movers = $dashboard->movers();

        $itemIds = $attention['rows']->pluck('stock.item_id')->merge($movers->pluck('item_id'))->unique()->values()->all();

        return view('dashboard', [
            'site' => $currentSite->get(),
            'alerts' => $alerts,
            'attention' => $attention,
            'movers' => $movers,
            'orders' => $dashboard->ordersToChase($alerts),
            'usage' => $dashboard->weeklyUsage($itemIds),
            // Orders under way, drafts included, shown next to each item so nobody orders it twice (SPEC 5.3a).
            'inProgress' => PurchaseOrderLine::inProgress($itemIds, $currentSite->id()),
            'canOrder' => Site::query()->active()->get()->contains(fn (Site $s) => $request->user()->can('create', [PurchaseOrder::class, $s])),
            'totalValue' => $dashboard->totalValue(),
            'byCategory' => $dashboard->valueByCategory(),
            'coverage' => $dashboard->minimumCoverage(),
            'consumption' => $dashboard->consumption(),
            'topMachines' => $dashboard->topMachines(),
            'dormant' => $dashboard->dormant(),
        ]);
    }
}
