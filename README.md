# HRIS Backend — hris-be-msr

Laravel REST API untuk Smart Attendance HRIS yang terhubung dengan frontend React PWA `hris-fe-msr`.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3+ |
| Database | MySQL 8 |
| Authentication | Laravel Sanctum |
| Authorization | Role middleware dan ownership checks |
| ORM | Eloquent |
| File Storage | Laravel Filesystem private disk |
| Testing | Laravel Feature Tests |
| Code Style | Laravel Pint |

## API Base URL

```text
http://localhost:8000/api/v1
```

## Module Status

| Module | Backend | Frontend | Status |
|---|:---:|:---:|---|
| Authentication & Role Access | ✅ | ✅ | Synced |
| Dashboard | ✅ | ✅ | Synced |
| Employee Management | ✅ | ✅ | Synced |
| Attendance, Leave, Shift & Report | ✅ | ✅ | Synced |
| Department, Position & Branch | ✅ | ✅ | Completed |
| Employee Manager Relation | ✅ | ✅ | Completed |
| Employee Profile & Emergency Contact | ✅ | ✅ | Completed |
| **Employee Document Management** | ✅ | ✅ | **Completed** |

## Employee Document Management

Dokumen Employee disimpan pada private disk:

```text
storage/app/private/employee-documents
```

File tidak mempunyai public URL. Download hanya dilakukan melalui endpoint terautentikasi yang memeriksa role atau ownership.

Format yang didukung:

```text
PDF
JPG / JPEG
PNG
WEBP
```

Ukuran maksimal: **10 MB**.

Metadata yang disimpan:

```text
category
title
description
labels
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

Kategori:

```text
identity
tax
employment
education
certification
medical
payroll
other
```

### Employee Self-Service

```http
GET /api/v1/document-categories
GET /api/v1/documents/my
GET /api/v1/documents/my/summary
GET /api/v1/documents/my/{employeeDocument}
GET /api/v1/documents/my/{employeeDocument}/download
```

Employee hanya dapat melihat dan mengunduh dokumen miliknya sendiri.

### Admin dan HR

```http
GET    /api/v1/employee-documents
GET    /api/v1/employee-documents/summary
GET    /api/v1/employees/{employee}/documents
POST   /api/v1/employees/{employee}/documents
GET    /api/v1/employees/{employee}/documents/{employeeDocument}
PATCH  /api/v1/employees/{employee}/documents/{employeeDocument}
POST   /api/v1/employees/{employee}/documents/{employeeDocument}/replace
GET    /api/v1/employees/{employee}/documents/{employeeDocument}/download
DELETE /api/v1/employees/{employee}/documents/{employeeDocument}
```

Admin dan HR dapat upload, edit metadata, replace file, download, dan delete. Manager dan Employee tidak dapat memakai management endpoints.

### Expiry Filters

```text
valid
expiring
expired
without_expiry
```

Endpoint list mendukung category, status, search, sort, pagination, dan `expires_within_days`. Global Admin/HR list juga mendukung `employee_id`.

### File Lifecycle

- Stored filename menggunakan UUID.
- SHA-256 checksum disimpan untuk setiap file.
- Replace mempertahankan document ID dan menaikkan version.
- Old file dihapus setelah database update berhasil.
- Delete menggunakan soft-delete metadata dan menghapus file fisik.
- Menghapus Employee juga membersihkan seluruh file dokumennya.

### Cleanup

Dry run:

```bash
php artisan documents:cleanup-orphans --dry-run
```

Delete orphan files:

```bash
php artisan documents:cleanup-orphans
```

Cleanup dijadwalkan setiap Minggu pukul 02:00 dengan overlap protection.

Dokumentasi lengkap:

```text
docs/employee-document-management.md
```

## Employee Profile & Emergency Contact

Backend menyediakan self-service dan Admin/HR profile management, extended personal data, completion summary, maksimal lima emergency contacts, dan satu primary contact.

Dokumentasi lengkap:

```text
docs/employee-profile-emergency-contact.md
```

## Audit Logging

Activity log menyimpan actor, module, action, endpoint, response status, location, serta request/response preview. Password dan token difilter. Uploaded file hanya dicatat sebagai filename, MIME type, dan ukuran—binary content tidak disimpan dalam audit payload.

## Testing and CI

```bash
composer test
vendor/bin/pint --test
```

Backend CI menjalankan:

- Composer validation.
- Dependency installation.
- MySQL migrations.
- Laravel Pint.
- Full feature test suite.
- Backend test-log artifact untuk diagnostics.

Coverage Employee Document mencakup secure upload, private download, role dan ownership checks, metadata update, replace/versioning, expiry filters, summary, missing file handling, orphan cleanup, Employee deletion cleanup, secure headers, dan audit records.

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

`php artisan storage:link` hanya diperlukan untuk asset public seperti face enrollment. Employee documents tetap berada pada private disk dan tidak boleh diekspos melalui symlink public.

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@hris.test` | `password123` |
| HR | `hr@hris.test` | `password123` |
| Manager | `manager@hris.test` | `password123` |
| Employee | `employee@hris.test` | `password123` |

## Definition of Done

Sebuah modul dianggap selesai setelah migration, model, service, API, authorization, validation, storage lifecycle, tests, CI, frontend integration, mobile acceptance, dan dokumentasi sinkron.

## Next Module

Belum ditetapkan. Pemilihan berikutnya mengikuti roadmap proyek setelah Employee Document Management selesai.
