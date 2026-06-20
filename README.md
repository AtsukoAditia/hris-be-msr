# HRIS Backend — `hris-be-msr`

Laravel REST API untuk **Smart Attendance HRIS**, terhubung dengan frontend React PWA `hris-fe-msr`.

> Status terakhir diverifikasi: 20 Juni 2026  
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

### Basic Payroll Foundation

Backend implementation is complete and the frontend workspace is the active integration milestone.

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

Detailed endpoint mapping is available in `docs/API_MATRIX.md`.

## Payroll Scope Limitations

The foundation release intentionally excludes:

- Employee payslip and PDF download.
- Payroll CSV/PDF reporting.
- Tax and social-security calculation.
- Post-finalization adjustment records.
- Multi-level payroll approval.

These are planned for Payroll Sprint 2.

## Security Rules

- All business endpoints use Sanctum authentication.
- Frontend role visibility is not treated as authorization.
- Payroll administration is restricted to Admin and HR.
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
docs/ARCHITECTURE.md
docs/DEVELOPMENT_GUIDE.md
```

## Next Focus

Frontend Basic Payroll Foundation: salary components, employee salary profiles, payroll periods, payroll processing, lifecycle actions, responsive states, tests, and documentation.
