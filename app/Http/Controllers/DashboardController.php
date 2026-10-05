<?php

namespace App\Http\Controllers;

use App\Services\Dashboard;
use App\Support\CurrentSite;
use Illuminate\View\View;

/**
 * Alerts and figures for the selected site; administrators may view all sites (SPEC 8).
 */
class DashboardController extends Controller
{
    public function __invoke(CurrentSite $currentSite): View
    {
        $dashboard = new Dashboard($currentSite->get());
        $alerts = $dashboard->alerts();

        return view('dashboard', [
            'site' => $currentSite->get(),
            'alerts' => $alerts,
            'onOrder' => $dashboard->onOrderFor($alerts),
            'totalValue' => $dashboard->totalValue(),
            'byCategory' => $dashboard->valueByCategory(),
            'coverage' => $dashboard->minimumCoverage(),
            'consumption' => $dashboard->consumption(),
            'topMachines' => $dashboard->topMachines(),
            'dormant' => $dashboard->dormant(),
        ]);
    }
}
