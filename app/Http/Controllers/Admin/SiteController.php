<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteRequest;
use App\Models\Site;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Site::class);

        return view('admin.sites.index', [
            'sites' => Site::query()->withCount(['machines', 'users'])->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Site::class);

        return view('admin.sites.form', $this->formData(new Site(['digest_hour' => 7, 'timezone' => 'America/Chicago', 'is_active' => true])));
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        Site::query()->create($request->validated());

        return redirect()->route('admin.sites.index')->with('success', __('Site created.'));
    }

    public function edit(Site $site): View
    {
        Gate::authorize('update', $site);

        return view('admin.sites.form', $this->formData($site));
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $site->update($request->validated());

        return redirect()->route('admin.sites.index')->with('success', __('Site updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Site $site): array
    {
        $zones = DateTimeZone::listIdentifiers(DateTimeZone::AMERICA);

        return [
            'site' => $site,
            'timezones' => array_combine($zones, $zones),
            'hours' => collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => sprintf('%02d:00', $h)])->all(),
        ];
    }
}
