<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeProfileChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_self_profile_changes_must_use_change_request(): void
    {
        $user = $this->user('Sensitive Employee', 'sensitive.employee@hris.test');
        $employee = $this->employee($user, [
            'birth_date' => '1997-05-10',
            'gender' => 'male',
        ]);
        EmployeeProfile::create([
            'employee_id' => $employee->id,
            'nationality' => 'Indonesia',
            'identity_address' => 'Alamat Lama',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile/me', [
            'phone' => '081200001111',
            'nationality' => 'Singapore',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nationality');

        $this->assertDatabaseMissing('employees', [
            'id' => $employee->id,
            'phone' => '081200001111',
        ]);
        $this->assertDatabaseHas('employee_profiles', [
            'employee_id' => $employee->id,
            'nationality' => 'Indonesia',
        ]);

        $this->patchJson('/api/v1/profile/me', [
            'phone' => '081200001111',
            'nationality' => 'Indonesia',
        ])
            ->assertOk()
            ->assertJsonPath('data.employee.phone', '081200001111');
    }

    public function test_employee_can_create_list_show_and_cancel_own_change_request(): void
    {
        $user = $this->user('Request Employee', 'request.employee@hris.test');
        $employee = $this->employee($user, ['birth_date' => '1996-02-01']);
        EmployeeProfile::create([
            'employee_id' => $employee->id,
            'nationality' => 'Indonesia',
            'tax_number' => 'TAX-OLD',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/change-requests', [
            'changes' => [
                'nationality' => 'Malaysia',
                'tax_number' => 'TAX-NEW',
            ],
            'reason' => 'Dokumen identitas dan data pajak telah diperbarui.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', EmployeeProfileChangeRequest::STATUS_PENDING)
            ->assertJsonPath('data.current_values.nationality', 'Indonesia')
            ->assertJsonPath('data.requested_changes.nationality', 'Malaysia')
            ->assertJsonPath('data.can_cancel', true);

        $requestId = $response->json('data.id');

        $this->getJson('/api/v1/profile/change-requests?status=pending')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $requestId);

        $this->getJson("/api/v1/profile/change-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.changes.0.field', 'nationality');

        $this->deleteJson("/api/v1/profile/change-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeProfileChangeRequest::STATUS_CANCELLED)
            ->assertJsonPath('data.can_cancel', false);

        $this->assertDatabaseHas('employee_profile_change_requests', [
            'id' => $requestId,
            'employee_id' => $employee->id,
            'status' => EmployeeProfileChangeRequest::STATUS_CANCELLED,
        ]);
    }

    public function test_change_request_rejects_direct_fields_noop_and_second_pending_request(): void
    {
        $user = $this->user('Validation Employee', 'validation.employee@hris.test');
        $employee = $this->employee($user);
        EmployeeProfile::create([
            'employee_id' => $employee->id,
            'nationality' => 'Indonesia',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/change-requests', [
            'changes' => ['phone' => '08123456789'],
            'reason' => 'Nomor telepon berubah.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('changes');

        $this->postJson('/api/v1/profile/change-requests', [
            'changes' => ['nationality' => 'Indonesia'],
            'reason' => 'Tidak ada perubahan.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('changes.nationality');

        $this->postJson('/api/v1/profile/change-requests', [
            'changes' => ['nationality' => 'Malaysia'],
            'reason' => 'Pembaruan kewarganegaraan.',
        ])->assertCreated();

        $this->postJson('/api/v1/profile/change-requests', [
            'changes' => ['marital_status' => 'married'],
            'reason' => 'Pembaruan status pernikahan.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('changes');
    }

    public function test_employee_cannot_access_or_cancel_another_employees_request(): void
    {
        $owner = $this->user('Owner Employee', 'owner.change@hris.test');
        $ownerEmployee = $this->employee($owner);
        $other = $this->user('Other Employee', 'other.change@hris.test');
        $this->employee($other);
        $changeRequest = EmployeeProfileChangeRequest::create([
            'employee_id' => $ownerEmployee->id,
            'requested_by' => $owner->id,
            'current_values' => ['nationality' => null],
            'requested_changes' => ['nationality' => 'Indonesia'],
            'reason' => 'Initial nationality data.',
            'status' => EmployeeProfileChangeRequest::STATUS_PENDING,
        ]);
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/profile/change-requests/{$changeRequest->id}")
            ->assertNotFound();
        $this->deleteJson("/api/v1/profile/change-requests/{$changeRequest->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('employee_profile_change_requests', [
            'id' => $changeRequest->id,
            'status' => EmployeeProfileChangeRequest::STATUS_PENDING,
        ]);
    }

    public function test_change_request_validates_sensitive_values_and_unique_identifiers(): void
    {
        $first = $this->employee($this->user('Existing Employee', 'existing.identifier@hris.test'));
        EmployeeProfile::create([
            'employee_id' => $first->id,
            'tax_number' => 'TAX-EXISTING',
        ]);
        $user = $this->user('New Employee', 'new.identifier@hris.test');
        $this->employee($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/change-requests', [
            'changes' => [
                'birth_date' => now()->addDay()->format('Y-m-d'),
                'gender' => 'other',
                'tax_number' => 'TAX-EXISTING',
            ],
            'reason' => 'Invalid identifiers.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'changes.birth_date',
                'changes.gender',
                'changes.tax_number',
            ]);
    }

    public function test_user_without_employee_record_cannot_use_profile_change_requests(): void
    {
        $user = $this->user('Standalone User', 'standalone.change@hris.test');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile/change-requests')->assertNotFound();
        $this->postJson('/api/v1/profile/change-requests', [
            'changes' => ['nationality' => 'Indonesia'],
            'reason' => 'Initial data.',
        ])->assertNotFound();
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
}
