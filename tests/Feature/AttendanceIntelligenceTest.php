<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createEmployee(): Employee
    {
        $user = User::factory()->create(['role' => 'employee']);
        return Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    }

    /** @test */
    public function testWhoIsInReturnsSummary(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee();
        $shift = Shift::factory()->create();

        Attendance::create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'attendance_date' => today(),
            'check_in_time' => now()->subHours(4),
            'status' => 'present',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/attendance/who-is-in');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'date' => today()->format('Y-m-d'),
                    'total_active' => 1, // admin has no employee record
                ],
            ]);

        $this->assertArrayHasKey('summary', $response->json('data'));
        $this->assertArrayHasKey('present', $response->json('data'));
    }

    /** @test */
    public function testWhoIsInClassifiesLateCorrectly(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee();
        $shift = Shift::factory()->create(['start_time' => '08:00']);

        Attendance::create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'attendance_date' => today(),
            'check_in_time' => today()->setTime(8, 30),
            'status' => 'late',
            'late_minutes' => 15,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/attendance/who-is-in');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data['late']));
        $this->assertEquals($employee->id, $data['late'][0]['employee_id']);
    }

    /** @test */
    public function testMonthlySummaryReturnsPerEmployeeStats(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee();

        for ($day = 1; $day <= 5; $day++) {
            Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => now()->startOfMonth()->addDays($day),
                'status' => $day === 1 ? 'late' : 'present',
                'late_minutes' => $day === 1 ? 20 : 0,
                'check_in_time' => now()->startOfMonth()->addDays($day)->setTime(8, 0),
            ]);
        }

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/attendance/monthly-summary?' . http_build_query([
                'year' => now()->year,
                'month' => now()->month,
            ]));

        $response->assertOk();
        $summaries = $response->json('data.summaries');
        $this->assertNotEmpty($summaries);
        $this->assertEquals(4, $summaries[0]['present']);
        $this->assertEquals(1, $summaries[0]['late']);
        $this->assertArrayHasKey('attendance_rate', $summaries[0]);
    }

    /** @test */
    public function testMonthlySummaryValidatesParameters(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/attendance/monthly-summary?year=2020&month=13');

        $response->assertStatus(422);
    }

    /** @test */
    public function testAnomaliesDetectsConsecutiveLates(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee();
        $shift = Shift::factory()->create(['start_time' => '08:00']);

        // Create 4 consecutive lates
        for ($i = 0; $i < 4; $i++) {
            Attendance::create([
                'employee_id' => $employee->id,
                'shift_id' => $shift->id,
                'attendance_date' => now()->subDays($i + 1)->toDateString(),
                'check_in_time' => now()->subDays($i + 1)->setTime(8, 30 + $i),
                'status' => 'late',
                'late_minutes' => 15 + $i * 5,
            ]);
        }

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/attendance/anomalies?months=3');

        $response->assertOk();
        $employees = $response->json('data.employees');
        $this->assertNotEmpty($employees);

        $found = collect($employees)->firstWhere('employee_id', $employee->id);
        $this->assertNotNull($found);
        $this->assertNotEmpty($found['flags']);
        $this->assertEquals('consecutive_lates', $found['flags'][0]['type']);
    }

    /** @test */
    public function testAnomaliesDetectsHighLateRate(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee();
        $shift = Shift::factory()->create(['start_time' => '08:00']);

        // Create 10 attendance records, 8 of them late
        for ($i = 0; $i < 10; $i++) {
            $isLate = $i < 8;
            Attendance::create([
                'employee_id' => $employee->id,
                'shift_id' => $shift->id,
                'attendance_date' => now()->subDays($i + 1)->toDateString(),
                'check_in_time' => now()->subDays($i + 1)->setTime($isLate ? 8 : 7, 50),
                'status' => $isLate ? 'late' : 'present',
                'late_minutes' => $isLate ? 10 : 0,
            ]);
        }

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/attendance/anomalies?months=3');

        $response->assertOk();
        $found = collect($response->json('data.employees'))->firstWhere('employee_id', $employee->id);
        $this->assertNotNull($found);
        $types = collect($found['flags'])->pluck('type')->values();
        $this->assertTrue($types->contains('high_late_rate'));
    }

    /** @test */
    public function testTrendReturnsDailyData(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee();

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => today(),
            'status' => 'present',
            'late_minutes' => 0,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/attendance/trend?days=7');

        $response->assertOk();
        $trend = $response->json('data.trend');
        $this->assertCount(8, $trend); // 7 days + today
        $this->assertArrayHasKey('date', $trend[0]);
        $this->assertArrayHasKey('present', $trend[0]);
        $this->assertArrayHasKey('late', $trend[0]);
    }

    /** @test */
    public function testTrendValidatesDaysParameter(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/attendance/trend?days=100');

        // Clamped to max 90
        $response->assertOk();
        $this->assertCount(91, $response->json('data.trend'));
    }

    /** @test */
    public function testUnauthenticatedAccessIsRejected(): void
    {
        $this->getJson('/api/v1/attendance/who-is-in')->assertStatus(401);
        $this->getJson('/api/v1/attendance/monthly-summary?year=2026&month=7')->assertStatus(401);
        $this->getJson('/api/v1/attendance/anomalies')->assertStatus(401);
        $this->getJson('/api/v1/attendance/trend')->assertStatus(401);
    }
}
