<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftScheduleVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_schedule_id' => $this->shift_schedule_id,
            'version' => $this->version,
            'changes' => $this->changes,
            'changed_by' => $this->changed_by,
            'action' => $this->action,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
