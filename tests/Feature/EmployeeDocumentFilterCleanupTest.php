<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeDocumentFilterCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('employee_documents');
    }

    public function test_admin_can_filter_documents_and_read_expiry_summary(): void
    {
        $admin = $this->user('Filter Admin', 'filter.admin@hris.test', 'admin');
        $first = $this->employee($this->user('First Filter Employee', 'filter.first@hris.test'));
        $second = $this->employee($this->user('Second Filter Employee', 'filter.second@hris.test'));

        $expired = $this->document($first, $admin, 'identity', 'Expired Identity', today()->subDay());
        $expiring = $this->document($first, $admin, 'certification', 'Cloud Certificate', today()->addDays(10));
        $valid = $this->document($second, $admin, 'employment', 'Long Contract', today()->addDays(90));
        $withoutExpiry = $this->document($second, $admin, 'education', 'Bachelor Degree', null);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/employee-documents?status=expiring&expires_within_days=30')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $expiring->id)
            ->assertJsonPath('data.data.0.expiry_status', 'expiring');

        $this->getJson('/api/v1/employee-documents?category=identity')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $expired->id);

        $this->getJson('/api/v1/employee-documents?search=Cloud')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $expiring->id);

        $this->getJson('/api/v1/employee-documents/summary?expires_within_days=30')
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.expired', 1)
            ->assertJsonPath('data.expiring', 1)
            ->assertJsonPath('data.valid', 1)
            ->assertJsonPath('data.without_expiry', 1);

        $this->getJson("/api/v1/employee-documents/summary?employee_id={$second->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.valid', 1)
            ->assertJsonPath('data.without_expiry', 1);

        $this->assertNotNull($valid);
        $this->assertNotNull($withoutExpiry);
    }

    public function test_self_summary_only_counts_the_authenticated_employee_documents(): void
    {
        $user = $this->user('Summary Owner', 'summary.owner@hris.test');
        $employee = $this->employee($user);
        $other = $this->employee($this->user('Other Summary', 'summary.other@hris.test'));
        $this->document($employee, $user, 'certification', 'Own Expiring', today()->addDays(5));
        $this->document($employee, $user, 'education', 'Own Degree', null);
        $this->document($other, $user, 'identity', 'Other Expired', today()->subDay());
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/documents/my/summary?expires_within_days=30')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.expiring', 1)
            ->assertJsonPath('data.expired', 0)
            ->assertJsonPath('data.without_expiry', 1);
    }

    public function test_cleanup_command_supports_dry_run_and_deletes_orphan_files(): void
    {
        $user = $this->user('Cleanup Owner', 'cleanup.owner@hris.test');
        $employee = $this->employee($user);
        $document = $this->document($employee, $user, 'identity', 'Existing Document', null);
        $orphanPath = "employees/{$employee->id}/orphan.pdf";
        Storage::disk('employee_documents')->put($orphanPath, 'orphan contents');
        Storage::disk('employee_documents')->delete($document->file_path);

        $this->artisan('documents:cleanup-orphans --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('orphan.pdf');
        Storage::disk('employee_documents')->assertExists($orphanPath);

        $this->artisan('documents:cleanup-orphans')
            ->assertSuccessful()
            ->expectsOutputToContain('orphan.pdf');
        Storage::disk('employee_documents')->assertMissing($orphanPath);
    }

    public function test_deleting_an_employee_removes_all_private_document_files(): void
    {
        $admin = $this->user('Employee Delete Admin', 'employee.delete.admin@hris.test', 'admin');
        $employeeUser = $this->user('Employee Delete Target', 'employee.delete.target@hris.test');
        $employee = $this->employee($employeeUser);
        $first = $this->document($employee, $admin, 'identity', 'First Document', null);
        $second = $this->document($employee, $admin, 'employment', 'Second Document', today()->addYear());
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/employees/{$employee->id}")
            ->assertOk();

        Storage::disk('employee_documents')->assertMissing($first->file_path);
        Storage::disk('employee_documents')->assertMissing($second->file_path);
        $this->assertSoftDeleted('employee_documents', ['id' => $first->id]);
        $this->assertSoftDeleted('employee_documents', ['id' => $second->id]);
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        Sanctum::actingAs($this->user('Invalid Filter HR', 'invalid.filter@hris.test', 'hr'));

        $this->getJson('/api/v1/employee-documents?status=unknown&category=unknown&expires_within_days=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'category',
                'expires_within_days',
            ]);
    }

    private function document(
        Employee $employee,
        User $uploader,
        string $category,
        string $title,
        $expiryDate,
    ): EmployeeDocument {
        $name = str($title)->slug().'.pdf';
        $path = "employees/{$employee->id}/{$name}";
        Storage::disk('employee_documents')->put($path, $title);

        return EmployeeDocument::create([
            'employee_id' => $employee->id,
            'uploaded_by' => $uploader->id,
            'category' => $category,
            'title' => $title,
            'disk' => 'employee_documents',
            'file_path' => $path,
            'original_name' => $name,
            'stored_name' => $name,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => strlen($title),
            'checksum_sha256' => hash('sha256', $title),
            'version' => 1,
            'issue_date' => today()->subYear(),
            'expiry_date' => $expiryDate,
            'is_confidential' => true,
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
