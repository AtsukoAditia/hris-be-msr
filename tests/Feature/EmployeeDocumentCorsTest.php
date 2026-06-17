<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeDocumentCorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_download_exposes_filename_and_security_headers_to_frontend(): void
    {
        Storage::fake('employee_documents');

        $user = User::create([
            'name' => 'CORS Document Owner',
            'email' => 'cors.document@hris.test',
            'password' => 'password123',
            'role' => 'employee',
            'is_active' => true,
        ]);
        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-CORS',
            'nik' => 'NIK-CORS',
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);
        $path = "employees/{$employee->id}/cors-contract.pdf";
        Storage::disk('employee_documents')->put($path, 'private document contents');
        $document = EmployeeDocument::create([
            'employee_id' => $employee->id,
            'uploaded_by' => $user->id,
            'category' => 'employment',
            'title' => 'CORS Contract',
            'disk' => 'employee_documents',
            'file_path' => $path,
            'original_name' => 'cors-contract.pdf',
            'stored_name' => 'cors-contract.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 25,
            'checksum_sha256' => hash('sha256', 'private document contents'),
            'version' => 1,
            'is_confidential' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this
            ->withHeader('Origin', 'http://localhost:5173')
            ->get("/api/v1/documents/my/{$document->id}/download")
            ->assertOk()
            ->assertDownload('cors-contract.pdf');

        $exposed = strtolower((string) $response->headers->get('access-control-expose-headers'));

        $this->assertStringContainsString('content-disposition', $exposed);
        $this->assertStringContainsString('content-length', $exposed);
        $this->assertStringContainsString('x-content-type-options', $exposed);
    }
}
