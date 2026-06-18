<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionService
{
    private const ATTACHMENT_DISK = 'local';

    private const ATTACHMENT_DIR = 'attendance-corrections';

    public function create(
        Employee $employee,
        User $requester,
        array $data,
        ?UploadedFile $attachment = null,
    ): AttendanceCorrectionRequest {
        return DB::transaction(function () use ($employee, $data, $attachment): AttendanceCorrectionRequest {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $date = Carbon::parse($data['attendance_date'])->toDateString();

            $duplicate = AttendanceCorrectionRequest::query()
                ->forEmployee($employee->id)
                ->pending()
                ->whereDate('correction_date', $date)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'attendance_date' => 'Masih ada permintaan koreksi yang menunggu review untuk tanggal ini.',
                ]);
            }

            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $date)
                ->first();

            $type = $data['correction_type'];
            $requestedCheckIn = $this->combine($date, $data['requested_check_in'] ?? null);
            $requestedCheckOut = $this->combine($date, $data['requested_check_out'] ?? null);

            $payload = [
                'attendance_id' => $attendance?->id,
                'correction_date' => $date,
                'correction_type' => $type,
                'original_check_in' => $attendance?->check_in_time,
                'original_check_out' => $attendance?->check_out_time,
                'requested_check_in' => in_array($type, [AttendanceCorrectionRequest::TYPE_CHECK_IN, AttendanceCorrectionRequest::TYPE_BOTH], true) ? $requestedCheckIn : null,
                'requested_check_out' => in_array($type, [AttendanceCorrectionRequest::TYPE_CHECK_OUT, AttendanceCorrectionRequest::TYPE_BOTH], true) ? $requestedCheckOut : null,
                'reason' => $data['reason'],
                'status' => AttendanceCorrectionRequest::STATUS_PENDING,
            ];

            if ($attachment) {
                $path = $attachment->store(self::ATTACHMENT_DIR, self::ATTACHMENT_DISK);
                $payload['attachment_path'] = $path;
                $payload['attachment_name'] = $attachment->getClientOriginalName();
                $payload['attachment_mime'] = $attachment->getClientMimeType();
                $payload['attachment_size'] = $attachment->getSize();
            }

            return $employee->attendanceCorrectionRequests()->create($payload);
        });
    }

    public function cancel(AttendanceCorrectionRequest $request, User $requester): AttendanceCorrectionRequest
    {
        return DB::transaction(function () use ($request, $requester): AttendanceCorrectionRequest {
            $request = AttendanceCorrectionRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $employeeId = $requester->employee?->id;

            if (! $employeeId || (int) $request->employee_id !== (int) $employeeId) {
                throw ValidationException::withMessages([
                    'request' => 'Anda tidak dapat membatalkan permintaan ini.',
                ]);
            }

            $this->ensurePending($request);

            $request->update([
                'status' => AttendanceCorrectionRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $request->refresh();
        });
    }

    public function approve(
        AttendanceCorrectionRequest $request,
        User $reviewer,
        ?string $reviewNote = null,
    ): AttendanceCorrectionRequest {
        return DB::transaction(function () use ($request, $reviewer, $reviewNote): AttendanceCorrectionRequest {
            $employee = Employee::query()->lockForUpdate()->findOrFail($request->employee_id);
            $request = AttendanceCorrectionRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureReviewable($request, $reviewer);

            $date = $request->correction_date->toDateString();
            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $date)
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                $attendance = new Attendance([
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'status' => 'present',
                ]);
            }

            $oldValues = [
                'check_in_time' => $attendance->check_in_time?->toISOString(),
                'check_out_time' => $attendance->check_out_time?->toISOString(),
            ];

            if ($request->affectsCheckIn() && $request->requested_check_in) {
                $attendance->check_in_time = $request->requested_check_in;
                if (! $attendance->check_in_method) {
                    $attendance->check_in_method = 'correction';
                }
            }

            if ($request->affectsCheckOut() && $request->requested_check_out) {
                $attendance->check_out_time = $request->requested_check_out;
                if (! $attendance->check_out_method) {
                    $attendance->check_out_method = 'correction';
                }
            }

            $attendance->save();

            $newValues = [
                'check_in_time' => $attendance->check_in_time?->toISOString(),
                'check_out_time' => $attendance->check_out_time?->toISOString(),
            ];

            $request->update([
                'attendance_id' => $attendance->id,
                'status' => AttendanceCorrectionRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'review_note' => $reviewNote,
                'reviewed_at' => now(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);

            $this->recordAudit($reviewer, 'correction.approve', $request, $oldValues, $newValues);

            return $request->refresh();
        });
    }

    public function reject(
        AttendanceCorrectionRequest $request,
        User $reviewer,
        string $reviewNote,
    ): AttendanceCorrectionRequest {
        return DB::transaction(function () use ($request, $reviewer, $reviewNote): AttendanceCorrectionRequest {
            Employee::query()->lockForUpdate()->findOrFail($request->employee_id);
            $request = AttendanceCorrectionRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureReviewable($request, $reviewer);

            $request->update([
                'status' => AttendanceCorrectionRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'review_note' => $reviewNote,
                'reviewed_at' => now(),
            ]);

            $this->recordAudit($reviewer, 'correction.reject', $request, null, null);

            return $request->refresh();
        });
    }

    public function manualCorrect(User $actor, array $data): Attendance
    {
        return DB::transaction(function () use ($actor, $data): Attendance {
            $employee = Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);
            $date = Carbon::parse($data['attendance_date'])->toDateString();

            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $date)
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                $attendance = new Attendance([
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'status' => 'present',
                ]);
            }

            $oldValues = [
                'check_in_time' => $attendance->check_in_time?->toISOString(),
                'check_out_time' => $attendance->check_out_time?->toISOString(),
            ];

            if (! empty($data['check_in_time'])) {
                $attendance->check_in_time = $this->combine($date, $data['check_in_time']);
                if (! $attendance->check_in_method) {
                    $attendance->check_in_method = 'manual';
                }
            }

            if (! empty($data['check_out_time'])) {
                $attendance->check_out_time = $this->combine($date, $data['check_out_time']);
                if (! $attendance->check_out_method) {
                    $attendance->check_out_method = 'manual';
                }
            }

            $attendance->note = trim(($attendance->note ? $attendance->note.' | ' : '').'Koreksi manual: '.$data['reason']);
            $attendance->save();

            $newValues = [
                'check_in_time' => $attendance->check_in_time?->toISOString(),
                'check_out_time' => $attendance->check_out_time?->toISOString(),
            ];

            $this->recordManualAudit($actor, $employee->id, $attendance->id, $oldValues, $newValues, $data['reason']);

            return $attendance->refresh();
        });
    }

    public function transform(AttendanceCorrectionRequest $request, ?User $actor = null): array
    {
        $request->loadMissing([
            'employee.user:id,name,email',
            'reviewer:id,name,email,role',
        ]);

        $isPending = $request->isPending();
        $actorEmployeeId = $actor?->employee?->id;
        $isOwner = $actorEmployeeId && (int) $request->employee_id === (int) $actorEmployeeId;
        $canReview = $actor && $actor->isManager() && ! $isOwner && $isPending;

        return [
            'id' => $request->id,
            'employee_id' => $request->employee_id,
            'attendance_id' => $request->attendance_id,
            'correction_date' => $request->correction_date?->toDateString(),
            'correction_type' => $request->correction_type,
            'original_check_in' => $request->original_check_in?->toISOString(),
            'original_check_out' => $request->original_check_out?->toISOString(),
            'requested_check_in' => $request->requested_check_in?->toISOString(),
            'requested_check_out' => $request->requested_check_out?->toISOString(),
            'reason' => $request->reason,
            'review_note' => $request->review_note,
            'status' => $request->status,
            'has_attachment' => (bool) $request->attachment_path,
            'attachment_name' => $request->attachment_name,
            'old_values' => $request->old_values,
            'new_values' => $request->new_values,
            'employee' => [
                'id' => $request->employee?->id,
                'employee_number' => $request->employee?->employee_number,
                'name' => $request->employee?->user?->name,
            ],
            'reviewer' => $this->transformUser($request->reviewer),
            'can_cancel' => (bool) ($isPending && $isOwner),
            'can_review' => (bool) $canReview,
            'created_at' => $request->created_at?->toISOString(),
            'updated_at' => $request->updated_at?->toISOString(),
            'reviewed_at' => $request->reviewed_at?->toISOString(),
            'cancelled_at' => $request->cancelled_at?->toISOString(),
        ];
    }

    private function combine(string $date, ?string $time): ?Carbon
    {
        if (! $time) {
            return null;
        }

        return Carbon::parse($date.' '.$time);
    }

    private function ensurePending(AttendanceCorrectionRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan koreksi sudah diproses.',
            ]);
        }
    }

    private function ensureReviewable(AttendanceCorrectionRequest $request, User $reviewer): void
    {
        $this->ensurePending($request);

        $reviewerEmployeeId = $reviewer->employee?->id;

        if ($reviewerEmployeeId && (int) $request->employee_id === (int) $reviewerEmployeeId) {
            throw ValidationException::withMessages([
                'reviewer' => 'Reviewer tidak dapat memproses permintaan miliknya sendiri.',
            ]);
        }
    }

    private function recordAudit(User $actor, string $action, AttendanceCorrectionRequest $request, ?array $old, ?array $new): void
    {
        $actor->loadMissing('employee:id,user_id');

        ActivityLog::create([
            'user_id' => $actor->id,
            'user_name' => $actor->name,
            'user_email' => $actor->email,
            'user_role' => $actor->role,
            'module' => 'attendance_correction',
            'action' => $action,
            'method' => 'POST',
            'endpoint' => 'attendance/correction-requests/'.$request->id,
            'response_status' => 200,
            'request_payload' => [
                'correction_request_id' => $request->id,
                'employee_id' => $request->employee_id,
                'correction_type' => $request->correction_type,
                'old_values' => $old,
                'new_values' => $new,
            ],
            'description' => 'Attendance correction '.$action,
            'logged_at' => now(),
        ]);
    }

    private function recordManualAudit(User $actor, int $employeeId, int $attendanceId, array $old, array $new, string $reason): void
    {
        ActivityLog::create([
            'user_id' => $actor->id,
            'user_name' => $actor->name,
            'user_email' => $actor->email,
            'user_role' => $actor->role,
            'module' => 'attendance_correction',
            'action' => 'correction.manual',
            'method' => 'POST',
            'endpoint' => 'attendance/manual-correction',
            'response_status' => 200,
            'request_payload' => [
                'employee_id' => $employeeId,
                'attendance_id' => $attendanceId,
                'old_values' => $old,
                'new_values' => $new,
                'reason' => $reason,
            ],
            'description' => 'Manual attendance correction',
            'logged_at' => now(),
        ]);
    }

    private function transformUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    public function attachmentResponse(AttendanceCorrectionRequest $request)
    {
        if (! $request->attachment_path || ! Storage::disk(self::ATTACHMENT_DISK)->exists($request->attachment_path)) {
            throw ValidationException::withMessages([
                'attachment' => 'Lampiran tidak ditemukan.',
            ]);
        }

        return Storage::disk(self::ATTACHMENT_DISK)->download(
            $request->attachment_path,
            $request->attachment_name ?? 'attachment',
        );
    }
}
