<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('employee_documents');
    }

    public function test_employee_can_list_view_and_download_own_documents(): void
    {
        $user = $this->user('Self Document Owner', 'self.document@hris.test');
        $employee = $this->employee($user);
        $document = $this->document($employee, $user, 'own-contract.pdf');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/document-categories')
            ->assertOk()
            ->assertJsonFragment([
                'value' => 'identity',
                'label' => 'Identitas',
            ]);

        $this->getJson('/api/v1/documents/my')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $document->id)
            ->assertJsonPath('data.data.0.employee_id', $employee->id)
            ->assertJsonMissingPath('data.data.0.file_path');

        $this->getJson("/api/v1/documents/my/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.file.original_name', 'own-contract.pdf');

        $download = $this->get("/api/v1/documents/my/{$document->id}/download")
            ->assertOk()
            ->assertDownload('own-contract.pdf')
            ->assertHeader('x-content-type-options', 'nosniff');
        $cacheControl = (string) $download->headers->get('cache-control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'module' => 'documents',
            'action' => 'view',
            'endpoint' => "api/v1/documents/my/{$document->id}/download",
            'response_status' => 200,
        ]);
    }

    public function test_employee_cannot_access_another_employees_document(): void
    {
        $ownerUser = $this->user('First Owner', 'first.document@hris.test');
        $owner = $this->employee($ownerUser);
        $otherUser = $this->user('Other Employee', 'other.document@hris.test');
        $this->employee($otherUser);
        $document = $this->document($owner, $ownerUser, 'private.pdf');
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/documents/my/{$document->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->getJson("/api/v1/documents/my/{$document->id}/download")
            ->assertNotFound();
    }

    public function test_employee_and_manager_cannot_use_document_management_routes(): void
    {
        $employeeUser = $this->user('Restricted Employee', 'restricted.employee@hris.test');
        $employee = $this->employee($employeeUser);
        Sanctum::actingAs($employeeUser);

        $this->getJson('/api/v1/employee-documents')->assertForbidden();
        $this->postJson("/api/v1/employees/{$employee->id}/documents", [])->assertForbidden();

        $manager = $this->user('Restricted Manager', 'restricted.manager@hris.test', 'manager');
        $this->employee($manager);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/employee-documents')->assertForbidden();
        $this->getJson("/api/v1/employees/{$employee->id}/documents")->assertForbidden();
    }

    public function test_admin_nested_routes_reject_a_document_from_another_employee(): void
    {
        $admin = $this->user('Nested Admin', 'nested.admin@hris.test', 'admin');
        $first = $this->employee($this->user('First Employee', 'nested.first@hris.test'));
        $secondUser = $this->user('Second Employee', 'nested.second@hris.test');
        $second = $this->employee($secondUser);
        $document = $this->document($first, $admin, 'nested.pdf');
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/employees/{$second->id}/documents/{$document->id}")
            ->assertNotFound();
        $this->patchJson("/api/v1/employees/{$second->id}/documents/{$document->id}", [
            'title' => 'Unauthorized nested update',
        ])->assertNotFound();
        $this->deleteJson("/api/v1/employees/{$second->id}/documents/{$document->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('employee_documents', [
            'id' => $document->id,
            'employee_id' => $first->id,
            'title' => 'Employment Document',
            'deleted_at' => null,
        ]);
    }

    public function test_self_service_returns_not_found_when_user_has_no_employee_record(): void
    {
        Sanctum::actingAs($this->user('Standalone User', 'standalone.document@hris.test'));

        $this->getJson('/api/v1/documents/my')->assertNotFound();
        $this->getJson('/api/v1/documents/my/summary')->assertNotFound();
    }

    public function test_document_file_missing_from_storage_returns_not_found(): void
    {
        $user = $this->user('Missing File Owner', 'missing.document@hris.test');
        $employee = $this->employee($user);
        $document = $this->document($employee, $user, 'missing.pdf');
        Storage::disk('employee_documents')->delete($document->file_path);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/documents/my/{$document->id}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'File dokumen tidak ditemukan di penyimpanan.');
    }

    private function document(Employee $employee, User $uploader, string $name): EmployeeDocument
    {
        $path = "employees/{$employee->id}/{$name}";
        Storage::disk('employee_documents')->put($path, 'private document contents');

        return EmployeeDocument::create([
            'employee_id' => $employee->id,
            'uploaded_by' => $uploader->id,
            'category' => 'employment',
            'title' => 'Employment Document',
            'labels' => ['private'],
            'disk' => 'employee_documents',
            'file_path' => $path,
            'original_name' => $name,
            'stored_name' => $name,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 25,
            'checksum_sha256' => hash('sha256', 'private document contents'),
            'version' => 1,
            'issue_date' => today()->subYear(),
            'expiry_date' => today()->addYear(),
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
