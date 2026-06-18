<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileChangeReviewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_hr_can_list_requests_but_manager_cannot(): void
    {
        $employeeUser = $this->user('Review Target', 'review.target@hris.test');
        $employee = $this->employee($employeeUser);
        $changeRequest = $this->changeRequest($employee, $employeeUser);

        Sanctum::actingAs($this->user('Manager', 'review.manager@hris.test', 'manager'));
        $this->getJson('/api/v1/profile-change-requests')->assertForbidden();

        Sanctum::actingAs($this->user('HR', 'review.hr@hris.test', 'hr'));
        $this->getJson('/api/v1/profile-change-requests?status=pending&search=Review%20Target')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $changeRequest->id)
            ->assertJsonPath('data.data.0.employee.name', 'Review Target');

        $this->getJson("/api/v1/profile-change-requests/{$changeRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.requested_changes.nationality', 'Indonesia');
    }

    public function test_hr_rejection_requires_note_and_does_not_change_profile(): void
    {
        $employeeUser = $this->user('Reject Target', 'reject.target@hris.test');
        $employee = $this->employee($employeeUser);
        EmployeeProfile::create([
            'employee_id' => $employee->id,
            'marital_status' => 'single',
        ]);
        $changeRequest = $this->changeRequest(
            $employee,
            $employeeUser,
            ['marital_status' => 'single'],
            ['marital_status' => 'married'],
        );
        Sanctum::actingAs($this->user('HR Reviewer', 'reject.hr@hris.test', 'hr'));

        $this->postJson("/api/v1/profile-change-requests/{$changeRequest->id}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review_note');

        $this->postJson("/api/v1/profile-change-requests/{$changeRequest->id}/reject", [
            'review_note' => 'Dokumen pernikahan belum lengkap.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeProfileChangeRequest::STATUS_REJECTED);

        $this->assertDatabaseHas('employee_profiles', [
            'employee_id' => $employee->id,
            'marital_status' => 'single',
        ]);
    }

    public function test_reviewer_cannot_review_own_or_already_processed_request(): void
    {
        $hr = $this->user('Self Reviewing HR', 'self.review.hr@hris.test', 'hr');
        $ownRequest = $this->changeRequest($this->employee($hr), $hr);
        Sanctum::actingAs($hr);

        $this->postJson("/api/v1/profile-change-requests/{$ownRequest->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reviewer');

        $employeeUser = $this->user('Other Target', 'other.review.target@hris.test');
        $otherRequest = $this->changeRequest($this->employee($employeeUser), $employeeUser);

        $this->postJson("/api/v1/profile-change-requests/{$otherRequest->id}/approve")
            ->assertOk();
        $this->postJson("/api/v1/profile-change-requests/{$otherRequest->id}/reject", [
            'review_note' => 'Second processing attempt.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
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

    private function employee(User $user): Employee
    {
        return Employee::create([
            'user_id' => $user->id,
            'employee_number' => sprintf('EMP-%04d', $user->id),
            'nik' => sprintf('NIK-%04d', $user->id),
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);
    }

    private function changeRequest(
        Employee $employee,
        User $requester,
        array $currentValues = ['nationality' => null],
        array $requestedChanges = ['nationality' => 'Indonesia'],
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
