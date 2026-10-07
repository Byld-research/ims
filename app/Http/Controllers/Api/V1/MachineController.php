<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MachineResource;
use App\Http\Resources\MachineTypeResource;
use App\Models\Machine;
use App\Models\MachineType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Machine types with their parts lists, and the machine register (SPEC 7a).
 */
class MachineController extends Controller
{
    public function types(): AnonymousResourceCollection
    {
        return MachineTypeResource::collection(MachineType::query()->orderBy('code')->get());
    }

    public function type(MachineType $machineType): MachineTypeResource
    {
        return new MachineTypeResource($machineType->load(['partsList' => fn ($q) => $q->with('item')->orderBy('id')]));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['type' => ['nullable', 'string', 'size:1'], 'active' => ['nullable', 'boolean']]);
        $siteId = $this->siteId($request);

        return MachineResource::collection(Machine::query()
            ->with(['machineType', 'site'])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($request->input('type'), fn ($q, $code) => $q->whereHas('machineType', fn ($q) => $q->where('code', $code)))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('sku')
            ->get());
    }

    public function show(Request $request, Machine $machine): MachineResource
    {
        $this->ensureInScope($request, $machine->site_id);

        $machine->load(['machineType', 'site']);
        $resource = new MachineResource($machine);
        $resource->partsList = $machine->partsList()->loadMissing('item');

        return $resource;
    }
}
