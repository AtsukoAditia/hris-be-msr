<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeOwnRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_endpoint_returns_only_current_users_requests(): void
    {
        $actor = User::factory()->create(['role' => 'hr']);
        $actorEmployee = Employee::factory()->create(['user_id' => $actor->id]);
        $otherUser = User::factory()->create(['role' => 'employee']);
        $otherEmployee = Employee::factory()->create(['user_id' => $otherUser->id]);
        $policy = OvertimePolicy::factory()->create(['is_active' => true]);

        $own = OvertimeRequest::factory()->create([
            'employee_id' => $actorEmployee->id,
            'overtime_policy_id' => $policy->id,
        ]);
        OvertimeRequest::factory()->create([
            'employee_id' => $otherEmployee->id,
            'overtime_policy_id' => $policy->id,
        ]);

        $this->actingAs($actor)
            ->getJson('/api/v1/overtime-requests/my')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }
}
