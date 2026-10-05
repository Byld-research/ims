<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SiteSelectionController extends Controller
{
    public function __invoke(Request $request, CurrentSite $currentSite): RedirectResponse
    {
        Gate::authorize('switch-site');

        $validated = $request->validate([
            'site' => ['required', Rule::in([
                CurrentSite::ALL,
                ...Site::query()->active()->pluck('id')->map(fn ($id) => (string) $id),
            ])],
        ]);

        $currentSite->switchTo(
            $validated['site'] === CurrentSite::ALL ? null : Site::find($validated['site'])
        );

        return back();
    }
}
