<?php

namespace App\Http\Resources;

use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class LeaveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'leave_type' => $this->leave_type,
            'leave_type_id' => $this->leave_type_id,
            'leave_type_label' => $this->getLeaveTypeLabel(),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'total_days' => $this->total_days,
            'reason' => $this->reason,
            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? Storage::disk('public')->url($this->attachment) : null,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'rejection_reason' => $this->rejection_reason,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'employee' => new EmployeeBasicResource($this->whenLoaded('employee')),
            'approver' => new EmployeeBasicResource($this->whenLoaded('approver')),
            'leave_type_data' => new LeaveTypeBasicResource($this->whenLoaded('leaveType')),
        ];
    }

    private function getLeaveTypeLabel(): string
    {
        if ($this->leaveType) {
            return $this->leaveType->name;
        }

        return match ($this->leave_type) {
            'annual' => 'Cuti Tahunan',
            'sick' => 'Cuti Sakit',
            'emergency' => 'Cuti Darurat',
            'maternity' => 'Cuti Melahirkan',
            'paternity' => 'Cuti Ayah',
            'unpaid' => 'Cuti Tidak Dibayar',
            'other' => 'Lainnya',
            default => 'Cuti',
        };
    }

    private function getStatusLabel(): string
    {
        return match ($this->status) {
            Leave::STATUS_PENDING => 'Menunggu',
            Leave::STATUS_APPROVED => 'Disetujui',
            Leave::STATUS_REJECTED => 'Ditolak',
            Leave::STATUS_CANCELLED => 'Dibatalkan',
            default => '-',
        };
    }
}
