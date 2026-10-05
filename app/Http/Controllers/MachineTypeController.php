<?php

namespace App\Http\Controllers;

use App\Http\Requests\MachineTypeRequest;
use App\Models\MachineType;
use App\Services\PartsListImporter;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MachineTypeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MachineType::class);

        $query = MachineType::query()->withCount(['partsList', 'workCenters'])->orderBy('code');

        if (CsvExport::requested($request)) {
            return CsvExport::download('machine-types', ['Code', 'Name', 'Parts', 'Work centres', 'Active'],
                $query->lazy()->map(fn (MachineType $t) => [$t->code, $t->name, $t->parts_list_count, $t->work_centers_count, $t->is_active]));
        }

        return response()->view('machine-types.index', ['machineTypes' => $query->get()]);
    }

    public function show(Request $request, MachineType $machineType): Response
    {
        Gate::authorize('view', $machineType);

        $partsList = $machineType->partsList()
            ->with('item')
            ->join('items', 'items.id', '=', 'machine_type_items.item_id')
            ->orderBy('items.sku')
            ->select('machine_type_items.*')
            ->get();

        if (CsvExport::requested($request)) {
            return CsvExport::download('parts-list-'.strtolower($machineType->code), [...PartsListImporter::COLUMNS, 'name', 'uom'],
                $partsList->map(fn ($line) => [$line->item->sku, $line->reference, $line->qty_per_machine,
                    $line->is_consumable ? 'yes' : '', $line->note, $line->item->name, $line->item->uom]));
        }

        return response()->view('machine-types.show', [
            'machineType' => $machineType->load(['workCenters' => fn ($q) => $q->with('site')->orderBy('code')]),
            'partsList' => $partsList,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', MachineType::class);

        return view('machine-types.form', ['machineType' => new MachineType(['is_active' => true])]);
    }

    public function store(MachineTypeRequest $request): RedirectResponse
    {
        $machineType = MachineType::query()->create($request->validated());

        return redirect()->route('machine-types.show', $machineType)->with('success', __('Machine type created.'));
    }

    public function edit(MachineType $machineType): View
    {
        Gate::authorize('update', $machineType);

        return view('machine-types.form', compact('machineType'));
    }

    public function update(MachineTypeRequest $request, MachineType $machineType): RedirectResponse
    {
        $machineType->update($request->validated());

        return redirect()->route('machine-types.show', $machineType)->with('success', __('Machine type updated.'));
    }
}
