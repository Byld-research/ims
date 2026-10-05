<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderLine;
use App\Services\Dashboard;
use App\Support\CurrentSite;
use Illuminate\View\View;

/**
 * Alerts first, then the items that are moving, then the figures (SPEC 8).
 */
class DashboardController extends Controller
{
    public function __invoke(CurrentSite $currentSite): View
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
            'onOrder' => PurchaseOrderLine::onOrder($itemIds, $currentSite->id()),
            'totalValue' => $dashboard->totalValue(),
            'byCategory' => $dashboard->valueByCategory(),
            'coverage' => $dashboard->minimumCoverage(),
            'consumption' => $dashboard->consumption(),
            'topMachines' => $dashboard->topMachines(),
            'dormant' => $dashboard->dormant(),
        ]);
    }
}
