<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_and_view_an_employee_profile(): void
    {
        Sanctum::actingAs($this->user('Admin', 'profile.admin@hris.test', 'admin'));
        $employee = $this->employee($this->user('Target Employee', 'profile.target@hris.test'));

        $this->patchJson("/api/v1/employees/{$employee->id}/profile", [
            'phone' => '081234567890',
            'address' => 'Current address',
            'birth_date' => '1998-04-12',
            'gender' => 'male',
            'personal_email' => ' PERSONAL@EXAMPLE.COM ',
            'place_of_birth' => 'Jakarta',
            'marital_status' => 'MARRIED',
            'blood_type' => 'o+',
            'identity_address' => 'Identity address',
            'domicile_address' => 'Domicile address',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'tax_number' => 'NPWP-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.employee.phone', '081234567890')
            ->assertJsonPath('data.profile.personal_email', 'personal@example.com')
            ->assertJsonPath('data.profile.marital_status', 'married')
            ->assertJsonPath('data.profile.blood_type', 'O+')
            ->assertJsonPath('data.completion.total_fields', 12)
            ->assertJsonFragment(['primary_emergency_contact']);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'phone' => '081234567890',
            'gender' => 'male',
        ]);
        $this->assertDatabaseHas('employee_profiles', [
            'employee_id' => $employee->id,
            'personal_email' => 'personal@example.com',
            'tax_number' => 'NPWP-001',
        ]);

        $this->getJson("/api/v1/employees/{$employee->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.employee.name', 'Target Employee')
            ->assertJsonPath('data.profile.city', 'Jakarta');
    }

    public function test_employee_can_manage_direct_edit_fields_but_cannot_use_admin_profile_route(): void
    {
        $user = $this->user('Self Employee', 'self.profile@hris.test');
        $employee = $this->employee($user);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile/me', [
            'phone' => '089900001111',
            'personal_email' => 'self.personal@example.com',
            'domicile_address' => 'Jl. Self Service No. 1',
        ])
            ->assertOk()
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.profile.domicile_address', 'Jl. Self Service No. 1');

        $this->getJson('/api/v1/profile/me')
            ->assertOk()
            ->assertJsonPath('data.profile.personal_email', 'self.personal@example.com');

        $this->getJson("/api/v1/employees/{$employee->id}/profile")
            ->assertForbidden();
    }

    public function test_profile_validation_rejects_invalid_and_duplicate_identifiers(): void
    {
        Sanctum::actingAs($this->user('HR', 'profile.hr@hris.test', 'hr'));
        $first = $this->employee($this->user('First', 'profile.first@hris.test'));
        $second = $this->employee($this->user('Second', 'profile.second@hris.test'));

        EmployeeProfile::create([
            'employee_id' => $first->id,
            'personal_email' => 'duplicate@example.com',
            'tax_number' => 'TAX-DUPLICATE',
        ]);

        $this->patchJson("/api/v1/employees/{$second->id}/profile", [
            'birth_date' => now()->addDay()->format('Y-m-d'),
            'blood_type' => 'X',
            'personal_email' => 'duplicate@example.com',
            'tax_number' => 'TAX-DUPLICATE',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'birth_date',
                'blood_type',
                'personal_email',
                'tax_number',
            ]);
    }

    public function test_self_profile_returns_not_found_when_user_has_no_employee_record(): void
    {
        Sanctum::actingAs($this->user('Standalone User', 'standalone@hris.test'));

        $this->getJson('/api/v1/profile/me')
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->patchJson('/api/v1/profile/me', ['phone' => '0812'])
            ->assertNotFound();
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
}
