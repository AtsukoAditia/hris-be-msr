<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeBasicResource($this->whenLoaded('employee')),
            'shift_id' => $this->shift_id,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'schedule_date' => $this->schedule_date instanceof Carbon
                ? $this->schedule_date->format('Y-m-d')
                : $this->schedule_date,
            'is_day_off' => (bool) $this->is_day_off,
            'notes' => $this->notes,
            'status' => $this->status ?? 'draft',
            'is_published' => ($this->status ?? 'draft') === 'published',
            'version' => $this->version ?? 1,
            'published_at' => $this->published_at?->toISOString(),
            'published_by' => $this->published_by,
            'conflict_type' => $this->conflict_type,
            'conflict_message' => $this->conflict_message,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
