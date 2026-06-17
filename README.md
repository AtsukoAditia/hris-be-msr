# HRIS Backend — hris-be-msr

Laravel REST API untuk Smart Attendance HRIS yang terhubung dengan frontend React PWA `hris-fe-msr`.

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
| Department Master Data | ✅ | ✅ | Completed & Synced |
| Position Master Data | ✅ | ✅ | Completed & Synced |
| Branch / Work Location | ✅ | ✅ | Completed & Synced |
| Employee Manager Relation | ✅ | ⬜ | Backend Completed |

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

Branch mendukung:

- Unique uppercase code.
- Search code, name, address, dan timezone.
- Status dan `active_only` filter.
- Latitude dan longitude berpasangan.
- Latitude `-90` sampai `90`.
- Longitude `-180` sampai `180`.
- Radius absensi `1–50000` meter.
- IANA timezone validation.
- Active/inactive status.
- Soft delete.
- Assigned Branch deletion protection.
- Seeder idempotent dan restore.

Endpoints:

```text
GET    /branches
POST   /branches
GET    /branches/{branch}
PUT    /branches/{branch}
PATCH  /branches/{branch}
DELETE /branches/{branch}
```

## Role Access

| Action | Admin | HR | Manager | Employee |
|---|:---:|:---:|:---:|:---:|
| List & Detail | ✅ | ✅ | ✅ | ❌ |
| Create | ✅ | ✅ | ❌ | ❌ |
| Update | ✅ | ✅ | ❌ | ❌ |
| Delete | ✅ | ✅ | ❌ | ❌ |

Manager mendapatkan read-only access pada backend dan frontend.

## Employee Organization Contract

Foreign keys:

```text
employees.department_id → departments.id
employees.position_id   → positions.id
employees.branch_id     → branches.id
employees.manager_id    → employees.id
```

Relationships:

```php
Employee::departmentMaster()
Employee::positionMaster()
Employee::branch()
Employee::manager()
Employee::directReports()
Department::employees()
Department::positions()
Position::department()
Position::employees()
Branch::employees()
```

Request organisasi aktif:

```json
{
  "department_id": 1,
  "position_id": 5,
  "branch_id": 1,
  "manager_id": 8
}
```

Rules:

- Department harus aktif.
- Position harus aktif dan berasal dari Department yang dipilih.
- Branch harus aktif dan belum terhapus.
- Manager harus merupakan Employee aktif dan belum terhapus.
- Employee tidak dapat menjadi manager untuk dirinya sendiri.
- Relasi manager yang membuat circular hierarchy ditolak.
- `manager_id` nullable untuk pimpinan tertinggi atau Employee tanpa direct manager.
- Update tanpa `manager_id` mempertahankan manager sebelumnya.
- Update dengan `manager_id: null` menghapus relasi manager.
- Saat manager dihapus, `manager_id` seluruh direct report diubah menjadi `null`.
- Frontend mewajibkan Branch pada form Employee.
- Backend mempertahankan nullable `branch_id` untuk data atau consumer transisi.
- Update tanpa `branch_id` mempertahankan Branch Employee sebelumnya.

Employee response memuat:

```text
department_code
department_name
position_code
position_name
branch_code
branch_name
branch
manager_id
manager
manager_name
manager_employee_number
manager_position_name
```

Filter Employee:

```http
GET /api/v1/employees?department_id=1&position_id=5&branch_id=1&manager_id=8
GET /api/v1/employees?manager_id=none
```

Manager dropdown endpoint:

```http
GET /api/v1/employees/manager-options
GET /api/v1/employees/manager-options?exclude_employee_id=10
GET /api/v1/employees/manager-options?search=engineering&department_id=1&branch_id=1
```

Manager options hanya mengembalikan Employee dan akun aktif, maksimal 100 data, dengan identitas Department, Position, Branch, dan label siap pakai untuk dropdown.

## Seeders

```text
DepartmentSeeder
EmployeeDepartmentSeeder
PositionSeeder
EmployeePositionSeeder
BranchSeeder
EmployeeBranchSeeder
```

`EmployeeBranchSeeder` menghubungkan Employee lama tanpa Branch ke `HQ-JKT`.

## Tests and CI

Backend coverage:

- Department, Position, dan Branch CRUD.
- Authentication dan authorization.
- Manager read-only behavior.
- Branch search dan filters.
- Coordinates, radius, timezone, duplicate code, dan normalization.
- Soft delete dan assigned-Branch protection.
- Seeder idempotency dan restore.
- Employee Branch assignment, filter, search, update, compatibility, dan backfill.
- Employee manager assignment dan response summary.
- Self-reference dan circular hierarchy protection.
- Manager preserve, change, dan clear update flow.
- Employee filter dan search berdasarkan manager.
- Active manager options dan current Employee exclusion.
- Direct report cleanup ketika manager dihapus.
- Regression tests Department dan Position.

Frontend coverage:

- Branch tab dan CRUD UI.
- Location payload normalization.
- Manager read-only UI.
- Validation dan delete error feedback.
- Employee Branch normalization dan numeric payload.
- Active Branch dropdown dan required selection.
- Employee Branch table/detail dan API filter.

```bash
composer test
vendor/bin/pint --test
```

Backend CI menjalankan Composer validation, MySQL migrations, Pint, dan full tests. Frontend CI menjalankan ESLint, Vitest, dan production build.

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

- Backend migration, model, API, validation, authorization, seeder, tests, dan CI tersedia.
- Frontend service, UI, role states, tests, dan CI tersedia.
- Request dan response contract sinkron.
- Dropdown master tidak hardcoded.
- README kedua repository diperbarui.

## Next Module

```text
Employee Direct Manager Relation Frontend UI
```
