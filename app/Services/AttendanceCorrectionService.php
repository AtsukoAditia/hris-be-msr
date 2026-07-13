<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AttendanceCorrectionService
{
    /**
     * List correction requests with filters and authorization scope.
     */
    public function list(?Employee $employee, array $filters = []): LengthAwarePaginator
    {
        $query = AttendanceCorrectionRequest::query()
            ->with(['employee', 'attendance', 'reviewer']);

        if ($employee) {
            $query->where('employee_id', $employee->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['correction_type'])) {
            $query->where('correction_type', $filters['correction_type']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['employee_ids']) && is_array($filters['employee_ids'])) {
            $query->whereIn('employee_id', $filters['employee_ids']);
        }

        if (! empty($filters['department_id'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('correction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('correction_date', '<=', $filters['date_to']);
        }

        // Search filter
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%");
                  });
            });
        }

        // Sort filter
        $sort = $filters['sort'] ?? 'newest';
        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get detail of a correction request.
     */
    public function detail(AttendanceCorrectionRequest $correction): AttendanceCorrectionRequest
    {
        return $correction->load(['employee', 'attendance', 'reviewer']);
    }

    /**
     * Employee submits a correction request.
     */
    public function submit(Employee $employee, array $data, ?UploadedFile $attachment = null): AttendanceCorrectionRequest
    {
        return DB::transaction(function () use ($employee, $data, $attachment) {
            $correctionDate = $data['attendance_date'];

            // Find existing attendance for the date
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $correctionDate)
                ->first();

            // Check for duplicate: prevent resubmission if pending OR recent rejection/cancellation (within 7 days)
            $duplicate = AttendanceCorrectionRequest::where('employee_id', $employee->id)
                ->where('correction_date', $correctionDate)
                ->where('correction_type', $data['correction_type'])
                ->where(function ($q) {
                    $q->where('status', AttendanceCorrectionRequest::STATUS_PENDING)
                      ->orWhere(function ($sq) {
                          $sq->whereIn('status', [AttendanceCorrectionRequest::STATUS_REJECTED, AttendanceCorrectionRequest::STATUS_CANCELLED])
                             ->where('created_at', '>=', now()->subDays(7));
                      });
                })
                ->exists();

            if ($duplicate) {
                throw new \DomainException('Anda sudah memiliki permohonan koreksi untuk tanggal dan tipe yang sama (pending atau baru saja ditolak/dibatalkan dalam 7 hari terakhir).');
            }

            // Build requested times
            $requestedCheckIn = null;
            $requestedCheckOut = null;

            if (in_array($data['correction_type'], [AttendanceCorrectionRequest::TYPE_CHECK_IN, AttendanceCorrectionRequest::TYPE_BOTH], true)) {
                $requestedCheckIn = $correctionDate.' '.$data['requested_check_in'].':00';
            }

            if (in_array($data['correction_type'], [AttendanceCorrectionRequest::TYPE_CHECK_OUT, AttendanceCorrectionRequest::TYPE_BOTH], true)) {
                $requestedCheckOut = $correctionDate.' '.$data['requested_check_out'].':00';
            }

            // Handle attachment
            $attachmentPath = null;
            $attachmentName = null;
            $attachmentMime = null;
            $attachmentSize = null;

            if ($attachment) {
                $attachmentPath = $attachment->store('attendance-corrections/private', 'local');
                $attachmentName = $attachment->getClientOriginalName();
                $attachmentMime = $attachment->getMimeType();
                $attachmentSize = $attachment->getSize();
            }

            $correction = AttendanceCorrectionRequest::create([
                'employee_id' => $employee->id,
                'attendance_id' => $attendance?->id,
                'correction_date' => $correctionDate,
                'correction_type' => $data['correction_type'],
                'original_check_in' => $attendance?->check_in_time,
                'original_check_out' => $attendance?->check_out_time,
                'requested_check_in' => $requestedCheckIn,
                'requested_check_out' => $requestedCheckOut,
                'reason' => $data['reason'],
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_mime' => $attachmentMime,
                'attachment_size' => $attachmentSize,
                'status' => AttendanceCorrectionRequest::STATUS_PENDING,
            ]);

            ActivityLog::log(
                ActivityAction::SUBMIT,
                AttendanceCorrectionRequest::class,
                $correction->id,
                [
                    'correction_date' => $correctionDate,
                    'correction_type' => $data['correction_type'],
                ]
            );

            return $correction;
        });
    }

    /**
     * Cancel a pending correction request (employee).
     */
    public function cancel(AttendanceCorrectionRequest $correction): AttendanceCorrectionRequest
    {
        return DB::transaction(function () use ($correction) {
            $correction = AttendanceCorrectionRequest::where('id', $correction->id)
                ->lockForUpdate()
                ->first();

            if (! $correction->isPending()) {
                throw new \DomainException('Hanya permohonan dengan status pending yang dapat dibatalkan.');
            }

            $correction->update([
                'status' => AttendanceCorrectionRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            ActivityLog::log(
                ActivityAction::CANCEL,
                AttendanceCorrectionRequest::class,
                $correction->id,
                []
            );

            return $correction->refresh();
        });
    }

    /**
     * Approve a correction request and update attendance.
     */
    public function approve(AttendanceCorrectionRequest $correction, ?string $note = null): AttendanceCorrectionRequest
    {
        return DB::transaction(function () use ($correction, $note) {
            if (! $correction->isPending()) {
                throw new \DomainException('Hanya permohonan dengan status pending yang dapat disetujui.');
            }

            // Lock the row to prevent race condition
            $correction = AttendanceCorrectionRequest::where('id', $correction->id)
                ->lockForUpdate()
                ->first();

            if (! $correction->isPending()) {
                throw new \DomainException('Permohonan ini sudah diproses oleh reviewer lain.');
            }

            $originalValues = [
                'check_in_time' => $correction->original_check_in?->format('Y-m-d H:i:s'),
                'check_out_time' => $correction->original_check_out?->format('Y-m-d H:i:s'),
            ];

            // Update attendance
            if ($correction->attendance_id) {
                $attendance = Attendance::where('id', $correction->attendance_id)->lockForUpdate()->first();

                if ($attendance) {
                    if ($correction->affectsCheckIn()) {
                        $attendance->check_in_time = $correction->requested_check_in;
                    }

                    if ($correction->affectsCheckOut()) {
                        $attendance->check_out_time = $correction->requested_check_out;
                    }

                    $attendance->save();
                }
            }

            $newValues = [
                'check_in_time' => $correction->requested_check_in?->format('Y-m-d H:i:s'),
                'check_out_time' => $correction->requested_check_out?->format('Y-m-d H:i:s'),
            ];

            if ($correction->attendance_id) {
                $att = Attendance::find($correction->attendance_id);
                $newValues = [
                    'check_in_time' => $att?->check_in_time?->format('Y-m-d H:i:s'),
                    'check_out_time' => $att?->check_out_time?->format('Y-m-d H:i:s'),
                ];
            }

            $correction->update([
                'status' => AttendanceCorrectionRequest::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'review_note' => $note,
                'reviewed_at' => now(),
                'old_values' => $originalValues,
                'new_values' => $newValues,
            ]);

            ActivityLog::log(
                ActivityAction::APPROVE,
                AttendanceCorrectionRequest::class,
                $correction->id,
                [
                    'original_values' => $originalValues,
                    'new_values' => $newValues,
                ]
            );

            return $correction->refresh();
        });
    }

    /**
     * Reject a correction request.
     */
    public function reject(AttendanceCorrectionRequest $correction, string $note): AttendanceCorrectionRequest
    {
        return DB::transaction(function () use ($correction, $note) {
            $correction = AttendanceCorrectionRequest::where('id', $correction->id)
                ->lockForUpdate()
                ->first();

            if (! $correction->isPending()) {
                throw new \DomainException('Hanya permohonan dengan status pending yang dapat ditolak.');
            }

            $correction->update([
                'status' => AttendanceCorrectionRequest::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'review_note' => $note,
                'reviewed_at' => now(),
            ]);

            ActivityLog::log(
                ActivityAction::REJECT,
                AttendanceCorrectionRequest::class,
                $correction->id,
                ['review_note' => $note]
            );

            return $correction->refresh();
        });
    }

    /**
     * Manual correction by admin/HR (directly approved).
     */
    public function manualCorrection(Employee $employee, array $data): AttendanceCorrectionRequest
    {
        return DB::transaction(function () use ($employee, $data) {
            $correctionDate = $data['attendance_date'];

            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $correctionDate)
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                throw new \DomainException('Data absensi tidak ditemukan untuk tanggal tersebut.');
            }

            $originalValues = [
                'check_in_time' => $attendance->check_in_time?->format('Y-m-d H:i:s'),
                'check_out_time' => $attendance->check_out_time?->format('Y-m-d H:i:s'),
            ];

            // Update attendance directly
            if (in_array($data['correction_type'], [AttendanceCorrectionRequest::TYPE_CHECK_IN, AttendanceCorrectionRequest::TYPE_BOTH], true)) {
                $attendance->check_in_time = $correctionDate.' '.$data['requested_check_in'].':00';
            }

            if (in_array($data['correction_type'], [AttendanceCorrectionRequest::TYPE_CHECK_OUT, AttendanceCorrectionRequest::TYPE_BOTH], true)) {
                $attendance->check_out_time = $correctionDate.' '.$data['requested_check_out'].':00';
            }

            $attendance->save();

            $newValues = [
                'check_in_time' => $attendance->check_in_time?->format('Y-m-d H:i:s'),
                'check_out_time' => $attendance->check_out_time?->format('Y-m-d H:i:s'),
            ];

            // Build requested times for the correction record
            $requestedCheckIn = null;
            $requestedCheckOut = null;

            if (in_array($data['correction_type'], [AttendanceCorrectionRequest::TYPE_CHECK_IN, AttendanceCorrectionRequest::TYPE_BOTH], true)) {
                $requestedCheckIn = $correctionDate.' '.$data['requested_check_in'].':00';
            }

            if (in_array($data['correction_type'], [AttendanceCorrectionRequest::TYPE_CHECK_OUT, AttendanceCorrectionRequest::TYPE_BOTH], true)) {
                $requestedCheckOut = $correctionDate.' '.$data['requested_check_out'].':00';
            }

            $correction = AttendanceCorrectionRequest::create([
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id,
                'correction_date' => $correctionDate,
                'correction_type' => $data['correction_type'],
                'original_check_in' => $originalValues['check_in_time'],
                'original_check_out' => $originalValues['check_out_time'],
                'requested_check_in' => $requestedCheckIn,
                'requested_check_out' => $requestedCheckOut,
                'reason' => $data['reason'],
                'status' => AttendanceCorrectionRequest::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'old_values' => $originalValues,
                'new_values' => $newValues,
            ]);

            ActivityLog::log(
                ActivityAction::MANUAL_UPDATE,
                AttendanceCorrectionRequest::class,
                $correction->id,
                [
                    'original_values' => $originalValues,
                    'new_values' => $newValues,
                ]
            );

            return $correction->refresh();
        });
    }

    /**
     * Get private attachment path.
     */
    public function getAttachmentPath(AttendanceCorrectionRequest $correction): ?string
    {
        if (! $correction->attachment_path) {
            return null;
        }

        return Storage::disk('local')->path($correction->attachment_path);
    }
}
