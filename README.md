# HRIS Backend — `hris-be-msr`

Laravel REST API untuk **Smart Attendance HRIS**, terhubung dengan frontend React PWA `hris-fe-msr`.

> Status terakhir diverifikasi: 8 Juli 2026  
> Branch utama: `main`

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3+ |
| Database | MySQL 8 |
| Authentication | Laravel Sanctum |
| Authorization | Role middleware, ownership, and manager scope |
| Testing | PHPUnit / Laravel Feature Tests |
| Code Style | Laravel Pint |
| CI | GitHub Actions |

## API Base URL

```text
http://localhost:8000/api/v1
```

## Current Status

Core modules already integrated with the frontend:

- Authentication, RBAC, and role-based dashboard.
- Department, position, branch, employee, and manager relations.
- Employee profile, contacts, documents, and profile-change approval.
- Shift, attendance, GPS/photo/radius/QR, and attendance correction.
- Leave, leave administration, and overtime workflow.
- Reports, CSV export, and activity log viewer.
- Basic payroll foundation.
- Employee payslip and payroll reporting.
- Sprint 3 shift schedule self-service, team view, copy-week, and rotating shift.

## Sprint 3 Shift Schedule

Backend and frontend contracts are synchronized on `main`.

Available backend capabilities:

- Schedule list with employee, department, branch, shift, date range, and day-off filters.
- Single schedule create/update/delete.
- Day-off schedule handling with nullable `shift_id`.
- Bulk schedule assignment.
- Copy-week schedule duplication with optional employee filter.
- Rotating shift pattern generation.
- Employee self-service schedule endpoint.
- Manager/admin/HR team schedule endpoint.
- Legacy custom lookup endpoints for employee and date, registered before the resource route to avoid route shadowing.

Shift schedule endpoints are under:

```text
/api/v1/shift-schedules
/api/v1/shift-schedules/bulk
/api/v1/shift-schedules/copy-week
/api/v1/shift-schedules/rotating
/api/v1/shift-schedules/my-schedule
/api/v1/shift-schedules/team-schedule
/api/v1/shift-schedules/employee/{employee}
/api/v1/shift-schedules/date/{date}
```

## Basic Payroll Foundation

Backend implementation is complete and synchronized with the frontend payroll workspace.

Available backend capabilities:

- Salary component master with earning and deduction types.
- Fixed, percentage, and formula-ready configuration.
- Effective-dated employee salary profiles.
- Payroll periods and cutoff dates.
- Payroll generation from basic salary, attendance, absence, approved unpaid leave, and approved overtime actual minutes.
- Payroll item breakdown and calculation snapshots.
- Draft recalculation.
- Review, finalize, paid, and cancel lifecycle.
- Transaction and row-lock protection.
- Audit records and Admin/HR-only authorization.
- Integer-cent calculation before decimal persistence.

Payroll foundation endpoints are under:

```text
/api/v1/admin/salary-components
/api/v1/admin/employees/{employee}/salary-profiles
/api/v1/admin/salary-profiles/{salaryProfile}
/api/v1/admin/payroll-periods
/api/v1/admin/payrolls
```

## Payslip and Payroll Reporting

Payslip and payroll reporting are implemented on the backend and synchronized with the frontend.

Available backend capabilities:

- Employee payslip history.
- Authenticated employee payslip detail and download.
- Admin/HR payroll report summary.
- Payroll CSV/PDF export.
- Admin/HR payslip download for finalized or paid payroll records.
- Ownership and role enforcement through backend authorization.

Payslip and reporting endpoints are under:

```text
/api/v1/payslips
/api/v1/payslips/{payroll}
/api/v1/payslips/{payroll}/download
/api/v1/admin/payroll-reports/summary
/api/v1/admin/payroll-reports/export
/api/v1/admin/payrolls/{payroll}/payslip/download
```

Detailed endpoint mapping is available in `docs/API_MATRIX.md`.

## Current Scope Limitations

The current implementation intentionally excludes:

- Automatic tax and social-security calculation.
- Post-finalization payroll adjustment records.
- Multi-level payroll approval.
- Advanced shift schedule analytics/reporting.

These remain candidates for future sprints.

## Security Rules

- All business endpoints use Sanctum authentication.
- Frontend role visibility is not treated as authorization.
- Payroll administration is restricted to Admin and HR.
- Employee payslip access is ownership-scoped.
- Shift schedule self-service is employee-owned.
- Team schedule access is role and manager-scope controlled.
- Critical status transitions use database transactions and row locking.
- Draft payroll can be recalculated; finalized and paid payroll cannot be edited directly.
- Cancellation requires a reason and is blocked after payment.
- Sensitive documents remain on private storage.

## Testing and CI

Run locally:

```bash
composer test
vendor/bin/pint --test
```

Backend CI validates:

1. Composer metadata and dependency installation.
2. MySQL migrations.
3. Laravel Pint on changed files.
4. Full Laravel test suite.

## Local Setup

```bash
git clone https://github.com/AtsukoAditia/hris-be-msr.git
cd hris-be-msr
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Documentation

```text
docs/PROJECT_STATUS.md
docs/MODULES.md
docs/ROADMAP.md
docs/API_MATRIX.md
docs/PAYSLIP_REPORTING.md
docs/ARCHITECTURE.md
docs/DEVELOPMENT_GUIDE.md
```

## Next Focus

Stabilization: full smoke testing across frontend and backend `main`, then future payroll tax/social-security calculation, post-finalization adjustments, multi-level approval, and advanced shift schedule reporting.
