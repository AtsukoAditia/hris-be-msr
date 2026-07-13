<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftSwapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requester' => new EmployeeResource($this->whenLoaded('requester')),
            'target' => new EmployeeResource($this->whenLoaded('target')),
            'requester_schedule' => new ShiftScheduleResource($this->whenLoaded('requesterSchedule')),
            'target_schedule' => new ShiftScheduleResource($this->whenLoaded('targetSchedule')),
            'status' => $this->status,
            'reason' => $this->reason,
            'reviewed_by' => $this->reviewed_by,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
        ];
    }
}
