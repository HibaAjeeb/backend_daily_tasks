<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncConflictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity' => $this->entity,
            'entityId' => $this->entity_id,
            'clientData' => $this->client_data,
            'serverData' => $this->server_data,
            'detectedAt' => $this->detected_at?->toIso8601String(),
            'status' => $this->status,
        ];
    }
}
