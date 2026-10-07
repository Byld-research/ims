<?php

namespace App\Http\Resources;

use App\Models\Machine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Machine */
class MachineResource extends JsonResource
{
    /** Set by the controller for the single-machine answer: lines for this machine's revision. */
    public $partsList = null;

    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'type' => ['code' => $this->machineType->code, 'name' => $this->machineType->name],
            'name' => $this->name,
            'revision' => $this->revision,
            'site' => $this->site->code,
            'is_active' => $this->is_active,
            'parts_list' => $this->when($this->partsList !== null, fn () => PartsListLineResource::collection($this->partsList)),
        ];
    }
}
