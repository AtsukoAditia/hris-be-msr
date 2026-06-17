# Employee Document Management Backend

Status: **Backend implemented**

## Security Model

Employee documents are stored on the private `employee_documents` disk:

```text
storage/app/private/employee-documents
```

Files do not have public URLs and are only returned through authenticated download endpoints. Download responses include private/no-store cache directives and `X-Content-Type-Options: nosniff`.

For cross-origin frontend downloads, CORS exposes:

```text
Content-Disposition
Content-Length
Content-Type
X-Content-Type-Options
```

This allows the frontend to preserve the server-provided filename and inspect download metadata without exposing the file through a public URL.

Allowed files:

```text
PDF
JPG / JPEG
PNG
WEBP
```

Maximum size: **10 MB** per file.

Original filenames are sanitized. Stored filenames use UUID values. Every file records its MIME type, extension, byte size, and SHA-256 checksum.

## Data Model

```text
employees.id -> employee_documents.employee_id
users.id     -> employee_documents.uploaded_by
```

Document metadata:

```text
category
title
description
labels
disk
file_path
original_name
stored_name
mime_type
extension
size_bytes
checksum_sha256
version
issue_date
expiry_date
is_confidential
```

The table uses soft deletes. Physical files are removed when a document is deleted. Employee deletion also removes all private document files and soft-deletes their metadata.

## Categories

```text
identity       Identitas
tax            Perpajakan
employment     Kepegawaian
education      Pendidikan
certification  Sertifikasi
medical        Kesehatan
payroll        Payroll
other          Lainnya
```

Category options:

```http
GET /api/v1/document-categories
```

## Employee Self-Service

Available to authenticated users with an Employee record:

```http
GET /api/v1/documents/my
GET /api/v1/documents/my/summary
GET /api/v1/documents/my/{employeeDocument}
GET /api/v1/documents/my/{employeeDocument}/download
```

Employee users can only view and download documents owned by their Employee record. Cross-Employee access returns `404`.

## Admin and HR Endpoints

Global list and summary:

```http
GET /api/v1/employee-documents
GET /api/v1/employee-documents/summary
```

Employee-specific management:

```http
GET    /api/v1/employees/{employee}/documents
POST   /api/v1/employees/{employee}/documents
GET    /api/v1/employees/{employee}/documents/{employeeDocument}
PATCH  /api/v1/employees/{employee}/documents/{employeeDocument}
POST   /api/v1/employees/{employee}/documents/{employeeDocument}/replace
GET    /api/v1/employees/{employee}/documents/{employeeDocument}/download
DELETE /api/v1/employees/{employee}/documents/{employeeDocument}
```

Manager and Employee roles cannot use management endpoints.

## Upload Payload

Multipart form data:

```text
file                 required
category             required
title                required
description          optional
labels[]             optional, maximum 10
issue_date            optional, YYYY-MM-DD
expiry_date           optional, YYYY-MM-DD
is_confidential       optional, defaults to true
```

`issue_date` cannot be in the future. `expiry_date` cannot be earlier than `issue_date`.

## Replace Lifecycle

Replacing a file:

- Preserves the document ID and metadata.
- Generates a new private UUID filename.
- Recalculates MIME, size, extension, and checksum.
- Increments `version`.
- Removes the old file after the database update succeeds.
- Leaves failed old-file cleanup detectable by the orphan cleanup command.

## Filters

List endpoints support:

```text
category
status
expires_within_days
search
sort
per_page
```

Admin global list also supports:

```text
employee_id
```

Expiry statuses:

```text
valid
expiring
expired
without_expiry
```

Sort values:

```text
newest
oldest
expiry_asc
expiry_desc
```

`expires_within_days` defaults to 30 and accepts values from 1 to 365.

## Summary Response

```json
{
  "total": 4,
  "valid": 1,
  "expiring": 1,
  "expired": 1,
  "without_expiry": 1,
  "warning_days": 30
}
```

## Storage Cleanup

Dry run:

```bash
php artisan documents:cleanup-orphans --dry-run
```

Delete orphan files:

```bash
php artisan documents:cleanup-orphans
```

The command reports:

- Scanned file count.
- Orphan file paths.
- Deleted file count.
- Database document IDs whose files are missing.

Cleanup runs automatically every Sunday at 02:00 and uses `withoutOverlapping()`.

## Audit Logging

A missing `activity_logs` migration was discovered during document download testing and has been added. File uploads are converted to safe metadata before being written to JSON audit payloads; binary content and temporary paths are not stored.

## Automated Coverage

```text
tests/Feature/EmployeeDocumentManagementTest.php
tests/Feature/EmployeeDocumentAccessTest.php
tests/Feature/EmployeeDocumentFilterCleanupTest.php
tests/Feature/EmployeeDocumentCorsTest.php
```

Coverage includes:

- Secure upload and validation.
- Private storage and checksum generation.
- Metadata update and file replacement.
- Version increment and old-file removal.
- Soft delete and Employee deletion cleanup.
- Employee self-service list/detail/download.
- Cross-Employee ownership protection.
- Admin/HR role restrictions.
- Category, search, expiry filters, and summaries.
- Dry-run and real orphan cleanup.
- Missing-file handling.
- Secure response headers, CORS-exposed download metadata, and download audit records.
