# HRIS Backend — hris-be-msr

> Laravel REST API untuk Smart Attendance HRIS.

Backend terintegrasi dengan frontend React PWA [`hris-fe-msr`](https://github.com/AtsukoAditia/hris-fe-msr). Modul dinyatakan selesai setelah backend, frontend, role access, tests, CI, dan dokumentasi sinkron.

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
| Attendance, Leave, Shift & Report | ✅ | ✅ | Synced |
| **Department Master Data** | ✅ | ✅ | **Completed & Synced** |
| **Position Master Data** | ✅ | ✅ | **Completed & Synced** |
| **Branch / Work Location** | ✅ | ⬜ | **Backend Completed** |
| Employee Manager Relation | ⬜ | ⬜ | Planned |

## Organization Master Data

### Department

```text
id, code, name, description, is_active, timestamps, deleted_at
```

### Position

```text
id, department_id, code, name, description, is_active, timestamps, deleted_at
```

### Branch / Work Location

Table `branches`:

```text
id
code
name
address
latitude
longitude
radius_meters
timezone
is_active
created_at
updated_at
deleted_at
```

Karakteristik Branch:

- Unique code dan normalisasi uppercase.
- Search berdasarkan code, name, address, dan timezone.
- Filter `status` dan `active_only`.
- Latitude dan longitude harus diberikan sebagai pasangan.
- Latitude divalidasi pada rentang `-90` sampai `90`.
- Longitude divalidasi pada rentang `-180` sampai `180`.
- Radius attendance `1–50000` meter.
- IANA timezone validation.
- Active/inactive status.
- Soft delete untuk Branch yang belum digunakan Employee.
- Branch yang masih digunakan Employee tidak dapat dihapus.
- Seeder idempotent dan dapat restore Branch yang sebelumnya di-soft-delete.

Branch endpoints:

```text
GET    /branches
POST   /branches
GET    /branches/{branch}
PUT    /branches/{branch}
PATCH  /branches/{branch}
DELETE /branches/{branch}
```

Query parameters:

```text
search={keyword}
status=active|inactive|all
active_only=true|false
```

Contoh request:

```json
{
  "code": "HQ-JKT",
  "name": "Head Office Jakarta",
  "address": "Jakarta",
  "latitude": -6.2000000,
  "longitude": 106.8166667,
  "radius_meters": 150,
  "timezone": "Asia/Jakarta",
  "is_active": true
}
```

## Role Access Organization Master

| Action | Admin | HR | Manager | Employee |
|---|:---:|:---:|:---:|:---:|
| List & Detail | ✅ | ✅ | ✅ | ❌ |
| Create | ✅ | ✅ | ❌ | ❌ |
| Update | ✅ | ✅ | ❌ | ❌ |
| Delete | ✅ | ✅ | ❌ | ❌ |

Manager memiliki read-only API access. Frontend akan mengikuti matrix yang sama.

## Employee Organization Relationships

Foreign keys:

```text
employees.department_id → departments.id
employees.position_id   → positions.id
employees.branch_id     → branches.id
```

Model relationships:

```php
Employee::departmentMaster()
Employee::positionMaster()
Employee::branch()
Department::employees()
Department::positions()
Position::department()
Position::employees()
Branch::employees()
```

Kolom legacy berikut masih dipertahankan selama masa transisi:

```text
employees.department
employees.position
```

### Employee Create & Update

Contract organisasi:

```json
{
  "department_id": 1,
  "position_id": 5,
  "branch_id": 1
}
```

Rules:

- Department harus aktif.
- Position harus aktif dan berasal dari Department yang dipilih.
- Branch yang dikirim harus aktif dan belum terhapus.
- `branch_id` masih nullable selama transisi frontend.
- Update lama yang belum mengirim `branch_id` mempertahankan Branch Employee saat ini.

### Employee Response

```json
{
  "department_id": 1,
  "department_code": "IT",
  "department_name": "Information Technology",
  "position_id": 5,
  "position_code": "SOFTWARE-ENGINEER",
  "position_name": "Software Engineer",
  "branch_id": 1,
  "branch_code": "HQ-JKT",
  "branch_name": "Head Office Jakarta",
  "branch": {
    "id": 1,
    "code": "HQ-JKT",
    "name": "Head Office Jakarta",
    "latitude": "-6.2000000",
    "longitude": "106.8166667",
    "radius_meters": 150,
    "timezone": "Asia/Jakarta"
  }
}
```

Employee list filters:

```http
GET /api/v1/employees?department_id=1&position_id=5&branch_id=1
```

Employee search juga mencakup Branch code, name, dan address.

## Seeders

```text
DepartmentSeeder
EmployeeDepartmentSeeder
PositionSeeder
EmployeePositionSeeder
BranchSeeder
EmployeeBranchSeeder
```

`EmployeeBranchSeeder` menghubungkan Employee lama yang belum memiliki Branch ke `HQ-JKT`.

## Tests & CI

Backend coverage mencakup:

- Department, Position, dan Branch CRUD.
- Authentication dan role authorization.
- Manager read-only behavior.
- Branch search dan status filter.
- Branch normalization, coordinates, timezone, dan radius validation.
- Duplicate code validation.
- Soft delete dan assigned-Branch deletion protection.
- Seeder idempotency dan restore.
- Employee assignment melalui `department_id`, `position_id`, dan `branch_id`.
- Inactive Branch rejection.
- Employee Branch filter, search, update, compatibility, dan backfill.
- Forward dan reverse Eloquent relationships.
- Regression tests Department dan Position.

Jalankan:

```bash
composer test
vendor/bin/pint --test
```

Backend CI menjalankan Composer validation, dependency installation, MySQL migrations, Laravel Pint, dan full test suite.

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

## Definition of Done

- Migration, model, API, validation, authorization, dan seeder tersedia.
- Backend feature tests dan CI hijau.
- Frontend service, UI, role access, dan states tersedia.
- Request/response contract sinkron.
- Dropdown master tidak hardcoded.
- Frontend tests dan CI hijau.
- README kedua repository diperbarui.

## Next Step

```text
Branch / Work Location Frontend UI
→ Employee branch_id dropdown dan filter
→ Frontend tests dan CI
→ Update kedua README
```
