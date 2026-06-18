<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileChangeApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_approval_applies_changes_and_records_reviewer(): void
    {
        $employeeUser = $this->user('Approval Target', 'approval.target@hris.test');
        $employee = $this->employee($employeeUser, ['birth_date' => '1995-01-10']);
        EmployeeProfile::create([
            'employee_id' => $employee->id,
            'nationality' => 'Indonesia',
            'tax_number' => 'TAX-OLD-APPROVAL',
        ]);
        $changeRequest = $this->changeRequest(
            $employee,
            $employeeUser,
            [
                'birth_date' => '1995-01-10',
                'nationality' => 'Indonesia',
                'tax_number' => 'TAX-OLD-APPROVAL',
            ],
            [
                'birth_date' => '1995-02-11',
                'nationality' => 'Malaysia',
                'tax_number' => 'TAX-NEW-APPROVAL',
            ],
        );
        $admin = $this->user('Admin Reviewer', 'approval.admin@hris.test', 'admin');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/profile-change-requests/{$changeRequest->id}/approve", [
            'review_note' => 'Dokumen pendukung sudah diverifikasi.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeProfileChangeRequest::STATUS_APPROVED)
            ->assertJsonPath('data.reviewer.id', $admin->id)
            ->assertJsonPath('data.can_review', false);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'birth_date' => '1995-02-11',
        ]);
        $this->assertDatabaseHas('employee_profiles', [
            'employee_id' => $employee->id,
            'nationality' => 'Malaysia',
            'tax_number' => 'TAX-NEW-APPROVAL',
        ]);
        $this->assertDatabaseHas('employee_profile_change_requests', [
            'id' => $changeRequest->id,
            'status' => EmployeeProfileChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_approval_rejects_stale_profile_snapshot(): void
    {
        $employeeUser = $this->user('Stale Target', 'stale.target@hris.test');
        $employee = $this->employee($employeeUser);
        EmployeeProfile::create([
            'employee_id' => $employee->id,
            'nationality' => 'Indonesia',
        ]);
        $changeRequest = $this->changeRequest(
            $employee,
            $employeeUser,
            ['nationality' => 'Indonesia'],
            ['nationality' => 'Malaysia'],
        );
        $employee->profile()->update(['nationality' => 'Singapore']);
        Sanctum::actingAs($this->user('Stale Reviewer', 'stale.reviewer@hris.test', 'admin'));

        $this->postJson("/api/v1/profile-change-requests/{$changeRequest->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('changes');

        $this->assertDatabaseHas('employee_profiles', [
            'employee_id' => $employee->id,
            'nationality' => 'Singapore',
        ]);
        $this->assertDatabaseHas('employee_profile_change_requests', [
            'id' => $changeRequest->id,
            'status' => EmployeeProfileChangeRequest::STATUS_PENDING,
        ]);
    }

    public function test_approval_revalidates_unique_identifiers(): void
    {
        $employeeUser = $this->user('Unique Target', 'unique.target@hris.test');
        $employee = $this->employee($employeeUser);
        $changeRequest = $this->changeRequest(
            $employee,
            $employeeUser,
            ['tax_number' => null],
            ['tax_number' => 'TAX-CONFLICT'],
        );

        $otherEmployee = $this->employee($this->user('Conflict Owner', 'conflict.owner@hris.test'));
        EmployeeProfile::create([
            'employee_id' => $otherEmployee->id,
            'tax_number' => 'TAX-CONFLICT',
        ]);
        Sanctum::actingAs($this->user('Unique Reviewer', 'unique.reviewer@hris.test', 'admin'));

        $this->postJson("/api/v1/profile-change-requests/{$changeRequest->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tax_number');

        $this->assertDatabaseHas('employee_profile_change_requests', [
            'id' => $changeRequest->id,
            'status' => EmployeeProfileChangeRequest::STATUS_PENDING,
        ]);
    }

    private function user(string $name, string $email, string $role = 'employee'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function employee(User $user, array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'user_id' => $user->id,
            'employee_number' => sprintf('EMP-%04d', $user->id),
            'nik' => sprintf('NIK-%04d', $user->id),
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ], $attributes));
    }

    private function changeRequest(
        Employee $employee,
        User $requester,
        array $currentValues,
        array $requestedChanges,
    ): EmployeeProfileChangeRequest {
        return EmployeeProfileChangeRequest::create([
            'employee_id' => $employee->id,
            'requested_by' => $requester->id,
            'current_values' => $currentValues,
            'requested_changes' => $requestedChanges,
            'reason' => 'Profile data update request.',
            'status' => EmployeeProfileChangeRequest::STATUS_PENDING,
        ]);
    }
}
