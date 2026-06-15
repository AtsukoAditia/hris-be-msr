# HRIS Backend — hris-be-msr

> Laravel REST API untuk Smart Attendance HRIS.

Backend ini terintegrasi dengan frontend React PWA [`hris-fe-msr`](https://github.com/AtsukoAditia/hris-fe-msr). Modul dinyatakan selesai setelah backend, frontend, role access, tests, CI, dan dokumentasi sinkron.

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
| Branch / Work Location | ⬜ | ⬜ | Next Module |

## Organization Master Data

### Department

Table `departments`:

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

Endpoints:

```text
GET    /departments
POST   /departments
GET    /departments/{department}
PUT    /departments/{department}
PATCH  /departments/{department}
DELETE /departments/{department}
```

### Position

Table `positions`:

```text
id
department_id
code
name
description
is_active
created_at
updated_at
deleted_at
```

Karakteristik:

- Setiap Position wajib berada pada satu Department.
- Position code unik dan dinormalisasi uppercase.
- Department tujuan harus aktif dan tidak terhapus.
- Active/inactive status.
- Soft delete.
- Search berdasarkan Position dan Department.
- Filter `department_id`, `status`, dan `active_only`.
- Seeder idempotent dan dapat restore data yang di-soft-delete.

Endpoints:

```text
GET    /positions
POST   /positions
GET    /positions/{position}
PUT    /positions/{position}
PATCH  /positions/{position}
DELETE /positions/{position}
```

Query parameters:

```text
search={keyword}
department_id={id}
status=active|inactive|all
active_only=true|false
```

Contoh request:

```json
{
  "department_id": 1,
  "code": "SOFTWARE-ENGINEER",
  "name": "Software Engineer",
  "description": "Mengembangkan aplikasi perusahaan",
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

Frontend mengikuti matrix yang sama dan memberikan tampilan read-only untuk Manager.

## Employee Organization Relationships

Foreign keys:

```text
employees.department_id → departments.id
employees.position_id   → positions.id
```

Model relationships:

```php
Employee::departmentMaster()
Employee::positionMaster()
Department::employees()
Department::positions()
Position::department()
Position::employees()
```

Kolom legacy berikut masih dipertahankan selama masa transisi:

```text
employees.department
employees.position
```

Migration dan seeder melakukan backfill dari nilai legacy ke master data.

### Employee Create & Update

Contract frontend yang aktif:

```json
{
  "department_id": 1,
  "position_id": 5
}
```

Rules:

- Department harus aktif.
- Position harus aktif.
- Position harus berasal dari Department yang dipilih.
- Mismatch Department–Position menghasilkan validation error.
- Payload string lama masih didukung sementara untuk consumer legacy.

### Employee Response

```json
{
  "department_id": 1,
  "department_code": "IT",
  "department_name": "Information Technology",
  "position_id": 5,
  "position_code": "SOFTWARE-ENGINEER",
  "position_name": "Software Engineer",
  "department_master": {
    "id": 1,
    "code": "IT",
    "name": "Information Technology"
  },
  "position_master": {
    "id": 5,
    "department_id": 1,
    "code": "SOFTWARE-ENGINEER",
    "name": "Software Engineer"
  }
}
```

Employee list mendukung filter:

```http
GET /api/v1/employees?department_id=1&position_id=5
```

## Seeders

```text
DepartmentSeeder
EmployeeDepartmentSeeder
PositionSeeder
EmployeePositionSeeder
```

Urutan Organization Master pada `DatabaseSeeder` memastikan Department tersedia sebelum Position dan Employee relation backfill.

## Tests & CI

Backend coverage mencakup:

- Department dan Position CRUD.
- Authentication dan role authorization.
- Manager read-only behavior.
- Search dan filter.
- Code normalization dan duplicate validation.
- Active Department validation.
- Soft delete.
- Seeder idempotency dan restore.
- Employee assignment melalui `department_id` dan `position_id`.
- Position–Department mismatch rejection.
- Inactive Position rejection.
- Legacy alias dan backfill.
- Employee Department/Position filter dan update.
- Forward dan reverse Eloquent relationships.

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
- Frontend service, UI, role access, dan state tersedia.
- Request/response contract sinkron.
- Dropdown master tidak hardcoded.
- Frontend tests dan CI hijau.
- README kedua repository diperbarui.

## Next Module

```text
Branch / Work Location Master Data
```
