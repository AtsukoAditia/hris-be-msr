# Project Status — Smart Attendance HRIS Backend

> Last verified: 20 June 2026  
> Repository: `AtsukoAditia/hris-be-msr`  
> Main branch: `main`

## Module Status

| Module | Backend | Frontend | Status |
|---|:---:|:---:|---|
| Authentication, RBAC, dashboard | ✅ | ✅ | Completed |
| Organization and employee management | ✅ | ✅ | Completed |
| Employee profile, documents, and self-service | ✅ | ✅ | Completed |
| Shift, attendance, and attendance correction | ✅ | ✅ | Completed |
| Leave and overtime workflows | ✅ | ✅ | Completed |
| Reports and activity log | ✅ | ✅ | Completed |
| Basic payroll foundation | ✅ | 🔵 | Backend complete; frontend next |
| Payslip and payroll reporting | ⬜ | ⬜ | Planned Sprint 2 |

## Basic Payroll Foundation

Backend capabilities:

- Salary component master with earning and deduction types.
- Fixed, percentage, and formula-ready calculation configuration.
- Effective-dated employee salary profiles and component assignments.
- Payroll periods and cutoff dates.
- Draft generation and recalculation.
- Inputs from basic salary, attendance, absence, approved unpaid leave, and approved overtime actual minutes.
- Payroll items and calculation snapshots.
- Status flow: `draft → reviewed → finalized → paid`.
- Cancellation with mandatory reason.
- Transactions, row locking, audit records, and Admin/HR authorization.
- Integer-cent calculation before persisting decimal values.

## Payroll Rules

- Payroll generation requires an open period.
- Recalculation is allowed only while payroll is draft.
- Finalized or paid data cannot be changed directly.
- Paid or cancelled payroll cannot be cancelled again.
- Salary profiles used by finalized or paid payroll cannot be edited directly.
- One payroll record exists per employee and period.

## Scope Deferred to Sprint 2

- Employee payslip access and download.
- Payroll CSV/PDF report.
- Tax and social-security calculations.
- Post-finalization adjustment records.
- Multi-level payroll approval.

## Testing and CI

Backend CI validates Composer metadata, MySQL migrations, Laravel Pint, and the full Laravel test suite.

```bash
composer test
vendor/bin/pint --test
```

## Current Focus

Frontend payroll integration in `hris-fe-msr`:

- Salary components.
- Employee salary profiles.
- Payroll periods.
- Payroll list and detail.
- Generate, recalculate, review, finalize, paid, and cancel actions.
- Responsive UI, tests, lint, build, and documentation.

## Source of Truth

- Core routes: `routes/api_v1.php`
- Payroll routes: `routes/payroll.php`
- Module inventory: `docs/MODULES.md`
- Roadmap: `docs/ROADMAP.md`
- API matrix: `docs/API_MATRIX.md`
