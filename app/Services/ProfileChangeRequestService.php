<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\User;
use App\Support\EmployeeProfileFieldPolicy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProfileChangeRequestService
{
    public function __construct(private readonly EmployeeProfileService $profileService) {}

    public function create(Employee $employee, User $requester, array $changes, string $reason): EmployeeProfileChangeRequest
    {
        return DB::transaction(function () use ($employee, $requester, $changes, $reason): EmployeeProfileChangeRequest {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);

            if ($employee->profileChangeRequests()->pending()->exists()) {
                throw ValidationException::withMessages([
                    'changes' => 'Masih ada permintaan perubahan profil yang menunggu review.',
                ]);
            }

            $changes = Arr::only(
                EmployeeProfileFieldPolicy::normalize(
                    $changes,
                    EmployeeProfileFieldPolicy::APPROVAL_REQUIRED_FIELDS,
                ),
                EmployeeProfileFieldPolicy::APPROVAL_REQUIRED_FIELDS,
            );
            $currentValues = EmployeeProfileFieldPolicy::currentValues($employee, array_keys($changes));
            $actualChanges = [];

            foreach ($changes as $field => $value) {
                if ($value !== ($currentValues[$field] ?? null)) {
                    $actualChanges[$field] = $value;
                }
            }

            if ($actualChanges === []) {
                throw ValidationException::withMessages([
                    'changes' => 'Tidak ada perubahan data yang dapat diajukan.',
                ]);
            }

            return $employee->profileChangeRequests()->create([
                'requested_by' => $requester->id,
                'current_values' => Arr::only($currentValues, array_keys($actualChanges)),
                'requested_changes' => $actualChanges,
                'reason' => $reason,
                'status' => EmployeeProfileChangeRequest::STATUS_PENDING,
            ]);
        });
    }

    public function cancel(EmployeeProfileChangeRequest $changeRequest, User $requester): EmployeeProfileChangeRequest
    {
        return DB::transaction(function () use ($changeRequest, $requester): EmployeeProfileChangeRequest {
            $changeRequest = EmployeeProfileChangeRequest::query()
                ->whereKey($changeRequest->id)
                ->where('requested_by', $requester->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($changeRequest);

            $changeRequest->update([
                'status' => EmployeeProfileChangeRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $changeRequest->refresh();
        });
    }

    public function approve(
        EmployeeProfileChangeRequest $changeRequest,
        User $reviewer,
        ?string $reviewNote = null,
    ): EmployeeProfileChangeRequest {
        return DB::transaction(function () use ($changeRequest, $reviewer, $reviewNote): EmployeeProfileChangeRequest {
            $changeRequest = EmployeeProfileChangeRequest::query()
                ->whereKey($changeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureReviewable($changeRequest, $reviewer);

            $employee = Employee::query()->lockForUpdate()->findOrFail($changeRequest->employee_id);
            $fields = array_keys($changeRequest->requested_changes);
            $changes = EmployeeProfileFieldPolicy::normalize($changeRequest->requested_changes, $fields);
            $unknownFields = array_diff($fields, EmployeeProfileFieldPolicy::APPROVAL_REQUIRED_FIELDS);

            if ($unknownFields !== []) {
                throw ValidationException::withMessages([
                    'changes' => 'Permintaan memuat field yang tidak dapat diproses.',
                ]);
            }

            $profileId = $employee->profile()->value('id');
            Validator::make(
                $changes,
                Arr::only(EmployeeProfileFieldPolicy::rules($profileId), $fields),
            )->validate();

            $currentValues = EmployeeProfileFieldPolicy::currentValues($employee, $fields);
            $snapshotValues = EmployeeProfileFieldPolicy::normalize($changeRequest->current_values, $fields);

            if ($currentValues !== $snapshotValues) {
                throw ValidationException::withMessages([
                    'changes' => 'Data profil telah berubah setelah permintaan dibuat. Batalkan dan ajukan permintaan baru.',
                ]);
            }

            $this->profileService->update($employee, $changes);
            $changeRequest->update([
                'status' => EmployeeProfileChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'review_note' => $reviewNote,
                'reviewed_at' => now(),
            ]);

            return $changeRequest->refresh();
        });
    }

    public function reject(
        EmployeeProfileChangeRequest $changeRequest,
        User $reviewer,
        string $reviewNote,
    ): EmployeeProfileChangeRequest {
        return DB::transaction(function () use ($changeRequest, $reviewer, $reviewNote): EmployeeProfileChangeRequest {
            $changeRequest = EmployeeProfileChangeRequest::query()
                ->whereKey($changeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureReviewable($changeRequest, $reviewer);
            $changeRequest->update([
                'status' => EmployeeProfileChangeRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'review_note' => $reviewNote,
                'reviewed_at' => now(),
            ]);

            return $changeRequest->refresh();
        });
    }

    public function transform(EmployeeProfileChangeRequest $changeRequest): array
    {
        $changeRequest->loadMissing([
            'employee.user:id,name,email',
            'requester:id,name,email,role',
            'reviewer:id,name,email,role',
        ]);

        return [
            'id' => $changeRequest->id,
            'employee_id' => $changeRequest->employee_id,
            'status' => $changeRequest->status,
            'reason' => $changeRequest->reason,
            'review_note' => $changeRequest->review_note,
            'current_values' => $changeRequest->current_values,
            'requested_changes' => $changeRequest->requested_changes,
            'changes' => collect($changeRequest->requested_changes)
                ->map(fn ($value, string $field) => [
                    'field' => $field,
                    'current_value' => $changeRequest->current_values[$field] ?? null,
                    'requested_value' => $value,
                ])
                ->values()
                ->all(),
            'employee' => [
                'id' => $changeRequest->employee?->id,
                'employee_number' => $changeRequest->employee?->employee_number,
                'name' => $changeRequest->employee?->user?->name,
                'work_email' => $changeRequest->employee?->user?->email,
            ],
            'requester' => $this->transformUser($changeRequest->requester),
            'reviewer' => $this->transformUser($changeRequest->reviewer),
            'can_cancel' => $changeRequest->isPending(),
            'can_review' => $changeRequest->isPending(),
            'created_at' => $changeRequest->created_at?->toISOString(),
            'updated_at' => $changeRequest->updated_at?->toISOString(),
            'reviewed_at' => $changeRequest->reviewed_at?->toISOString(),
            'cancelled_at' => $changeRequest->cancelled_at?->toISOString(),
        ];
    }

    private function ensurePending(EmployeeProfileChangeRequest $changeRequest): void
    {
        if (! $changeRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan perubahan profil sudah diproses.',
            ]);
        }
    }

    private function ensureReviewable(EmployeeProfileChangeRequest $changeRequest, User $reviewer): void
    {
        $this->ensurePending($changeRequest);

        if ($changeRequest->requested_by === $reviewer->id) {
            throw ValidationException::withMessages([
                'reviewer' => 'Reviewer tidak dapat memproses permintaan miliknya sendiri.',
            ]);
        }
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
}
