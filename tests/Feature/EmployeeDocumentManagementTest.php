<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('employee_documents');
    }

    public function test_admin_can_upload_a_private_employee_document(): void
    {
        $admin = $this->user('Document Admin', 'document.admin@hris.test', 'admin');
        $employee = $this->employee($this->user('Document Owner', 'document.owner@hris.test'));
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/employees/{$employee->id}/documents", [
            'file' => UploadedFile::fake()->create('employment-contract.pdf', 256, 'application/pdf'),
            'category' => 'employment',
            'title' => 'Employment Contract',
            'description' => 'Signed permanent employment contract.',
            'labels' => ['contract', 'permanent', 'contract'],
            'issue_date' => today()->subYear()->format('Y-m-d'),
            'expiry_date' => today()->addYear()->format('Y-m-d'),
            'is_confidential' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.category', 'employment')
            ->assertJsonPath('data.category_label', 'Kepegawaian')
            ->assertJsonPath('data.file.original_name', 'employment-contract.pdf')
            ->assertJsonPath('data.file.version', 1)
            ->assertJsonPath('data.is_confidential', true)
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.disk');

        $document = EmployeeDocument::firstOrFail();
        Storage::disk('employee_documents')->assertExists($document->file_path);
        $this->assertSame(['contract', 'permanent'], $document->labels);
        $this->assertSame(hash('sha256', Storage::disk('employee_documents')->get($document->file_path)), $document->checksum_sha256);
    }

    public function test_upload_validation_rejects_unsafe_or_invalid_payloads(): void
    {
        $hr = $this->user('Document HR', 'document.hr@hris.test', 'hr');
        $employee = $this->employee($this->user('Validation Owner', 'document.validation@hris.test'));
        Sanctum::actingAs($hr);

        $this->postJson("/api/v1/employees/{$employee->id}/documents", [
            'file' => UploadedFile::fake()->create('payload.exe', 100, 'application/octet-stream'),
            'category' => 'unknown',
            'title' => '',
            'labels' => array_map(fn (int $number) => "label-{$number}", range(1, 11)),
            'issue_date' => today()->format('Y-m-d'),
            'expiry_date' => today()->subDay()->format('Y-m-d'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'file',
                'category',
                'title',
                'labels',
                'expiry_date',
            ]);

        $this->assertDatabaseCount('employee_documents', 0);
        $this->assertSame([], Storage::disk('employee_documents')->allFiles());
    }

    public function test_admin_can_update_metadata_and_replace_the_file(): void
    {
        $admin = $this->user('Replace Admin', 'replace.admin@hris.test', 'admin');
        $employee = $this->employee($this->user('Replace Owner', 'replace.owner@hris.test'));
        Sanctum::actingAs($admin);

        $documentId = $this->postJson("/api/v1/employees/{$employee->id}/documents", [
            'file' => UploadedFile::fake()->createWithContent('old.pdf', "%PDF-1.4\nold file contents"),
            'category' => 'employment',
            'title' => 'Old Contract',
            'issue_date' => today()->subYear()->format('Y-m-d'),
        ])->assertCreated()->json('data.id');

        $document = EmployeeDocument::findOrFail($documentId);
        $oldPath = $document->file_path;
        $oldChecksum = $document->checksum_sha256;

        $this->patchJson("/api/v1/employees/{$employee->id}/documents/{$document->id}", [
            'title' => 'Updated Contract',
            'category' => 'certification',
            'labels' => ['updated'],
            'expiry_date' => today()->addMonths(6)->format('Y-m-d'),
            'is_confidential' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Contract')
            ->assertJsonPath('data.category', 'certification')
            ->assertJsonPath('data.is_confidential', false);

        $this->postJson("/api/v1/employees/{$employee->id}/documents/{$document->id}/replace", [
            'file' => UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\nnew file contents"),
        ])
            ->assertOk()
            ->assertJsonPath('data.file.original_name', 'new.pdf')
            ->assertJsonPath('data.file.version', 2);

        $document->refresh();
        Storage::disk('employee_documents')->assertMissing($oldPath);
        Storage::disk('employee_documents')->assertExists($document->file_path);
        $this->assertNotSame($oldChecksum, $document->checksum_sha256);
        $this->assertSame(2, $document->version);
    }

    public function test_delete_soft_deletes_metadata_and_removes_private_file(): void
    {
        $admin = $this->user('Delete Admin', 'delete.admin@hris.test', 'admin');
        $employee = $this->employee($this->user('Delete Owner', 'delete.owner@hris.test'));
        Sanctum::actingAs($admin);

        $documentId = $this->postJson("/api/v1/employees/{$employee->id}/documents", [
            'file' => UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf'),
            'category' => 'identity',
            'title' => 'Identity Document',
        ])->assertCreated()->json('data.id');
        $document = EmployeeDocument::findOrFail($documentId);
        $path = $document->file_path;

        $this->deleteJson("/api/v1/employees/{$employee->id}/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('employee_documents', ['id' => $document->id]);
        Storage::disk('employee_documents')->assertMissing($path);
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
