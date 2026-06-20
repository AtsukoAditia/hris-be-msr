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
| Basic payroll foundation | ✅ | ✅ | Completed |
| Payslip and payroll reporting | 🔵 | ⬜ | Backend implementation in review; frontend next |

## Basic Payroll Foundation

Completed capabilities:

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
- Responsive frontend workspace with component and mobile acceptance tests.

## Payslip and Payroll Reporting

Backend milestone scope:

- Employee-owned payslip history.
- Payslip detail with earning and deduction breakdown.
- Authenticated employee PDF download.
- Admin/HR protected payslip download.
- Payroll summary by period and consistent filters.
- CSV and PDF payroll report exports.
- Private no-store download responses.
- Ownership, status, and role regression tests.

## Payroll Rules

- Payroll generation requires an open period.
- Recalculation is allowed only while payroll is draft.
- Finalized or paid data cannot be changed directly.
- Paid or cancelled payroll cannot be cancelled again.
- Salary profiles used by finalized or paid payroll cannot be edited directly.
- One payroll record exists per employee and period.
- Employee payslips expose only finalized or paid records owned by the authenticated employee.
- Manager cannot access salary or payroll report endpoints.

## Testing and CI

Backend CI validates Composer metadata, MySQL migrations, Laravel Pint, and the full Laravel test suite.

```bash
composer test
vendor/bin/pint --test
```

## Current Focus

Complete backend CI and merge, then implement frontend:

- Employee payslip history and detail.
- Authenticated file downloads through Axios blob responses.
- Admin/HR payroll report summary.
- CSV and PDF export actions.
- Responsive employee and administrative pages.
- Component tests, lint, build, and mobile acceptance.

## Source of Truth

- Core routes: `routes/api_v1.php`
- Payroll routes: `routes/payroll.php`
- Payslip/report routes: `routes/payroll_reporting.php`
- Payslip/report contract: `docs/PAYSLIP_REPORTING.md`
- Module inventory: `docs/MODULES.md`
- Roadmap: `docs/ROADMAP.md`
- API matrix: `docs/API_MATRIX.md`
