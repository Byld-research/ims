<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\MachineRequest;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\Site;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Support\CsvExport;
use App\Support\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The machine register (SPEC 4.3, 5.8; screen 14).
 */
class MachineController extends Controller
{
    public function index(Request $request, CurrentSite $currentSite): Response
    {
        Gate::authorize('viewAny', Machine::class);

        $filters = $request->validate([
            'type' => ['nullable', 'integer', Rule::exists('machine_types', 'id')],
            'inactive' => ['nullable', 'boolean'],
        ]);

        $query = Machine::query()
            ->with(['site', 'machineType'])
            ->when($currentSite->id(), fn ($q, $siteId) => $q->where('site_id', $siteId))
            ->when($filters['type'] ?? null, fn ($q, $typeId) => $q->where('machine_type_id', $typeId))
            ->unless($filters['inactive'] ?? false, fn ($q) => $q->active())
            ->orderBy('sku');

        if (CsvExport::requested($request)) {
            return CsvExport::download('machines', ['SKU', 'Type', 'Name', 'Revision', 'Site', 'Active'],
                $query->lazy()->map(fn (Machine $m) => [$m->sku, $m->machineType->code, $m->name, $m->revision, $m->site->code, $m->is_active]));
        }

        return response()->view('machines.index', [
            'machines' => $query->get(),
            'filters' => $filters,
            'site' => $currentSite->get(),
            'types' => MachineType::query()->orderBy('code')->get()->mapWithKeys(fn (MachineType $t) => [$t->id => $t->label()])->all(),
        ]);
    }

    public function show(Request $request, Machine $machine): View
    {
        Gate::authorize('view', $machine);

        $machine->load(['site', 'machineType']);
        $partsList = $machine->partsList();

        // Stock at the machine's current site, next to each line (SPEC 5.8).
        $stocks = Stock::query()
            ->where('site_id', $machine->site_id)
            ->whereIn('item_id', $partsList->pluck('item_id'))
            ->get()
            ->keyBy('item_id');

        $issues = StockTransaction::query()->where('machine_id', $machine->id)->where('type', TransactionType::IssueMachine);

        return view('machines.show', [
            'machine' => $machine,
            'partsList' => $partsList,
            'stocks' => $stocks,
            'canIssue' => $machine->is_active && $request->user()->can('issue', [Stock::class, $machine->site]),
            // Consumption is what will correct the guessed minimum levels (SPEC 8).
            'consumption' => (clone $issues)->with(['item', 'site', 'user', 'machine', 'reasonCode', 'counterSite', 'purchaseOrderLine.purchaseOrder'])
                ->latest('id')->paginate(25, pageName: 'history'),
            'last90' => (clone $issues)->where('created_at', '>=', now()->subDays(90))
                ->selectRaw('COUNT(*) AS movements, COALESCE(-SUM(value), 0) AS value')->toBase()->first(),
        ]);
    }

    public function create(Request $request, CurrentSite $currentSite): View
    {
        Gate::authorize('create', Machine::class);

        $type = MachineType::query()->active()->find($request->integer('type'));

        return view('machines.form', $this->formData(new Machine([
            'machine_type_id' => $type?->id,
            'sku' => $type?->nextSku(),
            'name' => $type?->name,
            'revision' => '1.0',
            'site_id' => $currentSite->id(),
            'is_active' => true,
        ])));
    }

    public function store(MachineRequest $request): RedirectResponse
    {
        $machine = Machine::query()->create($request->validated());

        return redirect()->route('machines.show', $machine)->with('success', __('Machine :sku registered.', ['sku' => $machine->sku]));
    }

    public function edit(Machine $machine): View
    {
        Gate::authorize('update', $machine);

        return view('machines.form', $this->formData($machine));
    }

    public function update(MachineRequest $request, Machine $machine): RedirectResponse
    {
        $previousSite = $machine->site_id;
        $machine->update($request->validated());

        $message = $machine->site_id !== $previousSite
            ? __('Machine relocated to :site. Earlier movements stay recorded at the previous site.', ['site' => $machine->site->code])
            : __('Machine updated.');

        return redirect()->route('machines.show', $machine)->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Machine $machine): array
    {
        $types = MachineType::query()
            ->where(fn ($q) => $q->active()->orWhere('id', $machine->machine_type_id))
            ->orderBy('code')
            ->get();

        return [
            'machine' => $machine,
            'sites' => Site::query()->active()->orderBy('code')->get()->mapWithKeys(fn (Site $s) => [$s->id => $s->code.' · '.$s->name])->all(),
            'types' => $types,
            'locked' => $machine->exists && $machine->transactions()->exists(),
        ];
    }
}
