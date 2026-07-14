<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hr;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@company.com')->firstOrFail();
        $this->hr = User::where('email', 'hr@company.com')->firstOrFail();
        $this->employee = User::where('email', 'employee@company.com')->firstOrFail();
    }

    private function createOpenTraining(array $attrs = []): Training
    {
        return Training::create(array_merge([
            'title' => 'Test Training',
            'mode' => 'offline',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'max_participants' => 10,
            'cost' => 0,
            'status' => 'open',
            'created_by' => $this->admin->id,
        ], $attrs));
    }

    public function test_admin_can_create_training(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/trainings', [
                'title' => 'New Training',
                'mode' => 'offline',
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(6)->toDateString(),
                'max_participants' => 20,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('trainings', ['title' => 'New Training']);
    }

    public function test_employee_cannot_create_training(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/trainings', [
                'title' => 'New Training',
                'mode' => 'offline',
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(6)->toDateString(),
                'max_participants' => 20,
            ])
            ->assertStatus(403);
    }

    public function test_list_trainings_paginated(): void
    {
        Training::factory()->count(5)->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/trainings')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_filter_by_status(): void
    {
        Training::factory()->create(['status' => 'open', 'created_by' => $this->admin->id]);
        Training::factory()->create(['status' => 'closed', 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/trainings?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_training_with_available_slots(): void
    {
        $training = $this->createOpenTraining(['max_participants' => 5]);

        $this->actingAs($this->employee)
            ->getJson("/api/v1/trainings/{$training->id}")
            ->assertOk()
            ->assertJsonPath('data.available_slots', 5)
            ->assertJsonPath('data.is_open', true);
    }

    public function test_publish_draft_training(): void
    {
        $training = Training::factory()->create([
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/trainings/{$training->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_cannot_publish_non_draft_training(): void
    {
        $training = $this->createOpenTraining();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/trainings/{$training->id}/publish")
            ->assertStatus(422);
    }

    public function test_employee_can_enroll_open_training(): void
    {
        $training = $this->createOpenTraining();

        $this->actingAs($this->employee)
            ->postJson("/api/v1/trainings/{$training->id}/enroll")
            ->assertCreated();

        $this->assertDatabaseHas('training_enrollments', [
            'training_id' => $training->id,
            'employee_id' => $this->employee->employee?->id,
            'status' => 'registered',
        ]);
    }

    public function test_employee_cannot_enroll_twice(): void
    {
        $training = $this->createOpenTraining();
        $empId = $this->employee->employee?->id;

        TrainingEnrollment::create([
            'training_id' => $training->id,
            'employee_id' => $empId,
            'status' => 'registered',
        ]);

        $this->actingAs($this->employee)
            ->postJson("/api/v1/trainings/{$training->id}/enroll")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anda sudah terdaftar di pelatihan ini.');
    }

    public function test_enrollment_blocked_when_full(): void
    {
        $training = $this->createOpenTraining(['max_participants' => 1]);
        $empId = $this->employee->employee?->id;

        TrainingEnrollment::create([
            'training_id' => $training->id,
            'employee_id' => $empId,
            'status' => 'registered',
        ]);

        // admin trying to enroll another employee
        $anotherEmp = Employee::where('id', '!=', $empId)->first();
        if ($anotherEmp) {
            $this->actingAs($this->admin)
                ->postJson("/api/v1/trainings/{$training->id}/enroll")
                ->assertStatus(422);
        }
    }

    public function test_employee_can_cancel_enrollment(): void
    {
        $training = $this->createOpenTraining();
        $empId = $this->employee->employee?->id;
        $enrollment = TrainingEnrollment::create([
            'training_id' => $training->id,
            'employee_id' => $empId,
            'status' => 'registered',
        ]);

        $this->actingAs($this->employee)
            ->deleteJson("/api/v1/trainings/{$training->id}/enrollments/{$enrollment->id}")
            ->assertOk();

        $this->assertEquals('cancelled', $enrollment->fresh()->status);
    }

    public function test_my_enrollments_returns_employee_training_list(): void
    {
        $training = $this->createOpenTraining();
        $empId = $this->employee->employee?->id;
        TrainingEnrollment::create([
            'training_id' => $training->id,
            'employee_id' => $empId,
            'status' => 'registered',
        ]);

        $this->actingAs($this->employee)
            ->getJson('/api/v1/trainings/my')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_training_update(): void
    {
        $training = $this->createOpenTraining(['status' => 'draft']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/trainings/{$training->id}", [
                'title' => 'Updated Title',
                'status' => 'open',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.status', 'open');
    }

    public function test_training_delete(): void
    {
        $training = $this->createOpenTraining(['status' => 'draft']);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/trainings/{$training->id}")
            ->assertOk();

        $this->assertSoftDeleted('trainings', ['id' => $training->id]);
    }

    public function test_training_not_open_when_full(): void
    {
        $training = Training::create([
            'title' => 'Full Training',
            'mode' => 'offline',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'max_participants' => 1,
            'cost' => 0,
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        TrainingEnrollment::create([
            'training_id' => $training->id,
            'employee_id' => $this->employee->employee?->id,
            'status' => 'registered',
        ]);

        $this->assertFalse($training->fresh()->isOpen());
        $this->assertEquals(0, $training->fresh()->getAvailableSlots());
    }

    public function test_get_enrollments_for_training(): void
    {
        $training = $this->createOpenTraining();
        $empId = $this->employee->employee?->id;
        TrainingEnrollment::create([
            'training_id' => $training->id,
            'employee_id' => $empId,
            'status' => 'registered',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/trainings/{$training->id}/enrollments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_training_requiers_auth(): void
    {
        $this->getJson('/api/v1/trainings')->assertStatus(401);
    }
}
