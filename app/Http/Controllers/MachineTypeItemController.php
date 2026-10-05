<?php

namespace App\Http\Controllers;

use App\Http\Requests\MachineTypeItemRequest;
use App\Http\Requests\PartsListImportRequest;
use App\Models\MachineType;
use App\Models\MachineTypeItem;
use App\Services\PartsListImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Parts list lines. The list is informational (SPEC 5.8), so lines may be removed.
 */
class MachineTypeItemController extends Controller
{
    public function store(MachineTypeItemRequest $request, MachineType $machineType): RedirectResponse
    {
        $machineType->partsList()->create($request->validated());

        return back()->with('success', __('Part added.'));
    }

    public function update(MachineTypeItemRequest $request, MachineTypeItem $machineTypeItem): RedirectResponse
    {
        $machineTypeItem->update($request->validated());

        return back()->with('success', __('Part updated.'));
    }

    public function destroy(MachineTypeItem $machineTypeItem): RedirectResponse
    {
        Gate::authorize('update', $machineTypeItem->machineType);

        $machineTypeItem->delete();

        return back()->with('success', __('Part removed from the list.'));
    }

    public function import(PartsListImportRequest $request, MachineType $machineType, PartsListImporter $importer): RedirectResponse
    {
        $result = $importer->import($machineType, $request->file('file')->getRealPath());

        if ($result === null) {
            return back()->with('importErrors', $importer->errors())
                ->with('error', __('Nothing was imported. Fix the lines below and upload the file again.'));
        }

        return back()->with('success', __('Parts list imported: :created added, :updated updated.', $result));
    }
}
