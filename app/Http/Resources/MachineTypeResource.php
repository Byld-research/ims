<?php

namespace App\Http\Resources;

use App\Models\MachineType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MachineType */
class MachineTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'last_serial' => $this->last_serial,
            'is_active' => $this->is_active,
            'parts_list' => PartsListLineResource::collection($this->whenLoaded('partsList')),
        ];
    }
}
