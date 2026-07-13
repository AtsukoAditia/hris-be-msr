<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorrectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'attendance_id' => $this->attendance_id,
            'correction_date' => $this->correction_date?->format('Y-m-d'),
            'correction_type' => $this->correction_type,
            'original_check_in' => $this->original_check_in?->format('H:i:s'),
            'original_check_out' => $this->original_check_out?->format('H:i:s'),
            'requested_check_in' => $this->requested_check_in?->format('H:i:s'),
            'requested_check_out' => $this->requested_check_out?->format('H:i:s'),
            'reason' => $this->reason,
            'attachment_path' => $this->attachment_path,
            'attachment_name' => $this->attachment_name,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'review_note' => $this->review_note,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'employee' => new EmployeeBasicResource($this->whenLoaded('employee')),
            'attendance' => $this->whenLoaded('attendance'),
            'reviewer' => new EmployeeBasicResource($this->whenLoaded('reviewer')),
        ];
    }
}
