<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OvertimePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimePolicyPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_employee_can_list_active_overtime_policies(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        Employee::factory()->create(['user_id' => $user->id]);

        OvertimePolicy::factory()->create([
            'name' => 'Weekday Overtime',
            'is_active' => true,
        ]);
        OvertimePolicy::factory()->create([
            'name' => 'Inactive Policy',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/overtime-policies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Weekday Overtime')
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_unauthenticated_user_cannot_list_overtime_policies(): void
    {
        $this->getJson('/api/v1/overtime-policies')->assertUnauthorized();
    }
}
