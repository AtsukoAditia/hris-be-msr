<?php

namespace Tests\Feature;

use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmergencyContactApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_contact_is_primary_and_new_primary_replaces_it(): void
    {
        $user = $this->user('Contact Owner', 'contact.owner@hris.test');
        $employee = $this->employee($user);
        Sanctum::actingAs($user);

        $firstId = $this->postJson('/api/v1/profile/me/emergency-contacts', [
            'name' => 'First Contact',
            'relationship' => 'parent',
            'phone' => '0811111111',
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_primary', true)
            ->json('data.id');

        $secondId = $this->postJson('/api/v1/profile/me/emergency-contacts', [
            'name' => 'Second Contact',
            'relationship' => 'spouse',
            'phone' => '0822222222',
            'is_primary' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_primary', true)
            ->json('data.id');

        $this->assertDatabaseHas('emergency_contacts', [
            'id' => $firstId,
            'employee_id' => $employee->id,
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('emergency_contacts', [
            'id' => $secondId,
            'employee_id' => $employee->id,
            'is_primary' => true,
        ]);

        $this->deleteJson("/api/v1/profile/me/emergency-contacts/{$secondId}")
            ->assertOk();

        $this->assertSoftDeleted('emergency_contacts', ['id' => $secondId]);
        $this->assertDatabaseHas('emergency_contacts', [
            'id' => $firstId,
            'is_primary' => true,
        ]);
    }

    public function test_admin_can_manage_contacts_for_an_employee(): void
    {
        Sanctum::actingAs($this->user('Admin', 'contact.admin@hris.test', 'admin'));
        $employee = $this->employee($this->user('Managed Employee', 'contact.managed@hris.test'));

        $contactId = $this->postJson("/api/v1/employees/{$employee->id}/emergency-contacts", [
            'name' => 'Managed Parent',
            'relationship' => 'parent',
            'phone' => '0833333333',
            'email' => 'PARENT@EXAMPLE.COM',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'parent@example.com')
            ->json('data.id');

        $this->patchJson("/api/v1/employees/{$employee->id}/emergency-contacts/{$contactId}", [
            'name' => 'Updated Parent',
            'alternate_phone' => '0844444444',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Parent');

        $this->getJson("/api/v1/employees/{$employee->id}/emergency-contacts")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.alternate_phone', '0844444444');
    }

    public function test_employee_cannot_update_or_delete_another_employees_contact(): void
    {
        $owner = $this->employee($this->user('Owner', 'contact.first@hris.test'));
        $otherUser = $this->user('Other', 'contact.other@hris.test');
        $this->employee($otherUser);
        $contact = EmergencyContact::create([
            'employee_id' => $owner->id,
            'name' => 'Owner Contact',
            'relationship' => 'parent',
            'phone' => '0855555555',
            'is_primary' => true,
        ]);
        Sanctum::actingAs($otherUser);

        $this->patchJson("/api/v1/profile/me/emergency-contacts/{$contact->id}", [
            'name' => 'Unauthorized Update',
        ])->assertNotFound();

        $this->deleteJson("/api/v1/profile/me/emergency-contacts/{$contact->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('emergency_contacts', [
            'id' => $contact->id,
            'name' => 'Owner Contact',
            'deleted_at' => null,
        ]);
    }

    public function test_employee_cannot_create_more_than_five_contacts(): void
    {
        $user = $this->user('Limited Owner', 'contact.limit@hris.test');
        $this->employee($user);
        Sanctum::actingAs($user);

        foreach (range(1, 5) as $number) {
            $this->postJson('/api/v1/profile/me/emergency-contacts', [
                'name' => "Contact {$number}",
                'relationship' => 'relative',
                'phone' => "081000000{$number}",
            ])->assertCreated();
        }

        $this->postJson('/api/v1/profile/me/emergency-contacts', [
            'name' => 'Sixth Contact',
            'relationship' => 'friend',
            'phone' => '0810000006',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('emergency_contacts');
    }

    public function test_contact_validation_and_missing_employee_are_handled(): void
    {
        $user = $this->user('Validation Owner', 'contact.validation@hris.test');
        $this->employee($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/me/emergency-contacts', [
            'name' => 'Invalid Contact',
            'relationship' => 'coworker',
            'email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['relationship', 'phone', 'email']);

        Sanctum::actingAs($this->user('No Employee', 'contact.none@hris.test'));
        $this->getJson('/api/v1/profile/me/emergency-contacts')
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
