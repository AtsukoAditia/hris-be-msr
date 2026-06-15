# HRIS Backend — hris-be-msr

> Laravel REST API untuk Smart Attendance HRIS.

Backend ini terintegrasi dengan frontend React PWA [`hris-fe-msr`](https://github.com/AtsukoAditia/hris-fe-msr). Setiap modul hanya dinyatakan selesai setelah backend, frontend, tests, CI, role access, dan dokumentasi sudah sinkron.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3+ |
| Database | MySQL 8 |
| Authentication | Laravel Sanctum |
| Authorization | Role middleware |
| ORM | Eloquent |
| Testing | Laravel Feature Tests |
| Code Style | Laravel Pint |

## API Base URL

```text
http://localhost:8000/api/v1
```

## Current Status

| Module | Backend | Frontend | Status |
|---|:---:|:---:|:---:|
| Authentication & Role Access | ✅ | ✅ | Synced |
| Dashboard | ✅ | ✅ | Synced |
| Employee Management | ✅ | ✅ | Synced |
| Shift Management | ✅ | ✅ | Synced |
| Shift Schedule | ✅ | ✅ | Synced |
| Attendance GPS, Photo, Radius & QR | ✅ | ✅ | Synced |
| Leave Request & Approval | ✅ | ✅ | Synced |
| Reports & CSV Export | ✅ | ✅ | Synced |
| **Department Master Data** | ✅ | ✅ | **Completed & Synced** |
| Position Master Data | ⬜ | ⬜ | Next Module |
| Branch / Work Location | ⬜ | ⬜ | Planned |

## Department Master Data

Department module sudah selesai pada backend dan frontend.

### Database

Table `departments` menyediakan:

```text
id
code
name
description
is_active
created_at
updated_at
deleted_at
```

Karakteristik:

- Unique Department code.
- Active/inactive status.
- Soft delete.
- Idempotent seeder.
- Restore master data yang sebelumnya di-soft-delete.

### Department Endpoints

```text
GET    /departments
POST   /departments
GET    /departments/{department}
PUT    /departments/{department}
PATCH  /departments/{department}
DELETE /departments/{department}
```

### List Query Parameters

```text
search={keyword}
status=active|inactive|all
active_only=true|false
```

Example:

```http
GET /api/v1/departments?search=technology&status=active
```

### Create Request

```json
{
  "code": "IT",
  "name": "Information Technology",
  "description": "Mengelola sistem perusahaan",
  "is_active": true
}
```

Code otomatis di-trim dan diubah menjadi uppercase.

### Role Access

| Action | Admin | HR | Manager | Employee |
|---|:---:|:---:|:---:|:---:|
| List & Detail | ✅ | ✅ | ✅ | ❌ |
| Create | ✅ | ✅ | ❌ | ❌ |
| Update | ✅ | ✅ | ❌ | ❌ |
| Delete | ✅ | ✅ | ❌ | ❌ |

Frontend mengikuti role matrix yang sama. Manager mendapat tampilan read-only tanpa action controls.

## Employee–Department Relationship

Employee sudah terhubung ke Department master melalui:

```text
employees.department_id
```

Foreign key:

```text
employees.department_id → departments.id
```

Konfigurasi:

- Nullable selama masa transisi.
- `nullOnDelete` untuk menghindari kerusakan Employee record.
- Legacy string `employees.department` masih dipertahankan sementara.

### Model Relationships

```php
Employee::departmentMaster()
Department::employees()
```

Nama relationship Employee menggunakan `departmentMaster()` karena kolom legacy `department` masih tersedia.

### Legacy Backfill

Migration memetakan string lama ke Department master, termasuk:

| Legacy Value | Master Code |
|---|---|
| IT / Information Technology | IT |
| HR / Human Resource / Human Resources | HR |
| Finance | FIN |
| Operation / Operations | OPS |
| Management | MGT |
| Marketing | MKT |

Nilai legacy yang belum dikenal dapat dibuatkan Department master dengan kode unik.

### Employee Create & Update

Contract baru:

```json
{
  "department_id": 1
}
```

Backend hanya menerima Department yang aktif dan tidak terhapus.

Payload string lama masih didukung sementara agar consumer lama tidak langsung rusak:

```json
{
  "department": "Operations"
}
```

Frontend sekarang sudah menggunakan `department_id`, sehingga compatibility string dipertahankan hanya untuk migrasi consumer lain.

### Employee Response

```json
{
  "department_id": 1,
  "department": "IT",
  "department_code": "IT",
  "department_name": "Information Technology",
  "department_master": {
    "id": 1,
    "code": "IT",
    "name": "Information Technology"
  }
}
```

### Employee Filter

```http
GET /api/v1/employees?department_id=1
```

Search Employee juga mencakup Department code dan Department name melalui relationship.

## Department Seeders

```text
database/seeders/DepartmentSeeder.php
database/seeders/EmployeeDepartmentSeeder.php
```

Urutan pada `DatabaseSeeder`:

```text
DepartmentSeeder
ShiftSeeder
UserSeeder
EmployeeSeeder
EmployeeDepartmentSeeder
```

`EmployeeDepartmentSeeder` mengisi relation untuk Employee demo lama dan menyediakan master transisi bila diperlukan.

## Department Tests

Backend tests mencakup:

- Unauthenticated access.
- Role authorization.
- Manager read-only behavior.
- Department create, update, detail, list, search, dan filter.
- Code normalization.
- Duplicate code validation.
- Soft delete.
- Seeder idempotency dan restore.
- Employee create dengan `department_id`.
- Compatibility legacy alias.
- Inactive Department rejection.
- Employee filter berdasarkan `department_id`.
- Employee Department update.
- Legacy Employee backfill.
- Forward dan reverse Eloquent relationships.

Jalankan:

```bash
composer test
vendor/bin/pint --test
```

Backend CI menjalankan:

1. Composer validation.
2. Dependency installation.
3. Laravel environment setup.
4. MySQL migrations.
5. Laravel Pint.
6. Full Laravel test suite.

## Main API Areas

### Authentication

```text
POST /auth/login
GET  /auth/me
POST /auth/logout
POST /auth/change-password
```

### Employee

```text
GET    /employees
POST   /employees
GET    /employees/{employee}
PUT    /employees/{employee}
DELETE /employees/{employee}
GET    /employees/{employee}/profile
POST   /employees/{employee}/face-enrollment
```

### Shift & Schedule

```text
/api/v1/shifts
/api/v1/shift-schedules
```

### Attendance

```text
/api/v1/attendance
/api/v1/attendance/settings
/api/v1/attendance/qr/generate
```

### Leave & Reports

```text
/api/v1/leaves
/api/v1/reports/attendance
/api/v1/reports/leave
/api/v1/reports/employee
/api/v1/reports/export
```

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@hris.test` | `password123` |
| HR | `hr@hris.test` | `password123` |
| Manager | `manager@hris.test` | `password123` |
| Employee | `employee@hris.test` | `password123` |

## Module Development Workflow

Urutan wajib untuk setiap modul:

```text
1. Backend migration dan model
2. Backend validation, controller, routes, seeder, dan tests
3. Backend CI hijau dan merge
4. Frontend service dan UI
5. Frontend role access dan seluruh state UI
6. Integrasi frontend ke API tanpa mock atau hardcoded master data
7. Frontend tests dan CI hijau
8. Update README backend dan frontend
9. Merge frontend
10. Baru lanjut ke modul berikutnya
```

## Definition of Done

Sebuah modul HRIS dianggap selesai hanya apabila:

- Migration dan model tersedia.
- API tervalidasi.
- Authorization tersedia dan diuji.
- Seeder tersedia bila diperlukan.
- Backend feature tests lulus.
- Backend CI hijau.
- Frontend service dan UI tersedia.
- Contract frontend dan backend sinkron.
- Tidak ada dropdown master yang hardcoded.
- Loading, empty, error, success, dan validation feedback tersedia.
- Frontend role access sesuai backend.
- Frontend tests lulus.
- Frontend CI hijau.
- README kedua repository sudah diperbarui.

## Next Module

Module berikutnya:

```text
Position Master Data
```

Position baru dimulai setelah frontend Department di-merge dan dokumentasi backend/frontend telah sinkron.
