<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkCenterRequest;
use App\Models\MachineType;
use App\Models\Site;
use App\Models\Stock;
use App\Models\WorkCenter;
use App\Support\CsvExport;
use App\Support\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WorkCenterController extends Controller
{
    public function index(Request $request, CurrentSite $currentSite): Response
    {
        Gate::authorize('viewAny', WorkCenter::class);

        $filters = $request->validate(['inactive' => ['nullable', 'boolean']]);

        $query = WorkCenter::query()
            ->with(['site', 'machineType'])
            ->when($currentSite->id(), fn ($q, $siteId) => $q->where('site_id', $siteId))
            ->unless($filters['inactive'] ?? false, fn ($q) => $q->active())
            ->orderBy('site_id')
            ->orderBy('code');

        if (CsvExport::requested($request)) {
            return CsvExport::download('work-centres', ['Site', 'Code', 'Name', 'Machine type', 'Active'],
                $query->lazy()->map(fn (WorkCenter $w) => [$w->site->code, $w->code, $w->name, $w->machineType?->code, $w->is_active]));
        }

        return response()->view('work-centers.index', [
            'workCenters' => $query->get(),
            'filters' => $filters,
            'site' => $currentSite->get(),
        ]);
    }

    public function show(WorkCenter $workCenter): View
    {
        Gate::authorize('view', $workCenter);

        $workCenter->load(['site', 'machineType']);

        $partsList = $workCenter->machineType
            ? $workCenter->machineType->partsList()
                ->with('item')
                ->join('items', 'items.id', '=', 'machine_type_items.item_id')
                ->orderBy('items.sku')
                ->select('machine_type_items.*')
                ->get()
            : collect();

        // Stock at this work centre's own site, next to each line (SPEC 5.8).
        $stocks = Stock::query()
            ->where('site_id', $workCenter->site_id)
            ->whereIn('item_id', $partsList->pluck('item_id'))
            ->get()
            ->keyBy('item_id');

        return view('work-centers.show', compact('workCenter', 'partsList', 'stocks'));
    }

    public function create(CurrentSite $currentSite): View
    {
        Gate::authorize('create', WorkCenter::class);

        return view('work-centers.form', $this->formData(new WorkCenter([
            'site_id' => $currentSite->id(),
            'is_active' => true,
        ])));
    }

    public function store(WorkCenterRequest $request): RedirectResponse
    {
        $workCenter = WorkCenter::query()->create($request->validated());

        return redirect()->route('work-centers.show', $workCenter)->with('success', __('Work centre created.'));
    }

    public function edit(WorkCenter $workCenter): View
    {
        Gate::authorize('update', $workCenter);

        return view('work-centers.form', $this->formData($workCenter));
    }

    public function update(WorkCenterRequest $request, WorkCenter $workCenter): RedirectResponse
    {
        $workCenter->update($request->validated());

        return redirect()->route('work-centers.show', $workCenter)->with('success', __('Work centre updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(WorkCenter $workCenter): array
    {
        return [
            'workCenter' => $workCenter,
            'sites' => Site::query()->active()->orderBy('code')->get()->mapWithKeys(fn (Site $s) => [$s->id => $s->code.' · '.$s->name])->all(),
            'machineTypes' => MachineType::query()
                ->where(fn ($q) => $q->active()->orWhere('id', $workCenter->machine_type_id))
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn (MachineType $t) => [$t->id => $t->code.' · '.$t->name])
                ->all(),
            'siteLocked' => $workCenter->exists && $workCenter->transactions()->exists(),
        ];
    }
}
