<?php

namespace App\Http\Controllers;

use App\Support\CurrentSite;
use Illuminate\View\View;

/**
 * Placeholder until stage 7 builds the alert panel and figures (SPEC 8).
 */
class DashboardController extends Controller
{
    public function __invoke(CurrentSite $currentSite): View
    {
        return view('dashboard', ['site' => $currentSite->get()]);
    }
}
