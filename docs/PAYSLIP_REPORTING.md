# Payslip and Payroll Reporting

> Milestone: Sprint 2  
> Access model: authenticated employee ownership and Admin/HR reporting

## Employee Payslip

Employees can access only payroll records that:

- belong to their own employee profile; and
- have status `finalized` or `paid`.

Draft, reviewed, and cancelled records are not exposed as employee payslips.

Endpoints:

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/payslips` | Paginated payslip history for the authenticated employee |
| GET | `/api/v1/payslips/{payroll}` | Payslip detail and earning/deduction breakdown |
| GET | `/api/v1/payslips/{payroll}/download` | Authenticated PDF download |

The download response is generated on demand and uses private `no-store` cache headers. No public storage URL is created.

## Admin and HR Reporting

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/admin/payroll-reports/summary` | Aggregate record counts and financial totals |
| GET | `/api/v1/admin/payroll-reports/export?format=csv` | Filtered CSV export |
| GET | `/api/v1/admin/payroll-reports/export?format=pdf` | Filtered PDF report |
| GET | `/api/v1/admin/payrolls/{payroll}/payslip/download` | Download an employee payslip |

Supported filters:

- `payroll_period_id`
- `employee_id`
- `status`
- `search`

Financial totals exclude cancelled payroll records while status counts still report them.

## Security Rules

- All endpoints require Laravel Sanctum.
- Employee detail and download actions enforce `employee_id` ownership.
- Manager cannot access payroll reporting or administrative payslip downloads.
- Admin/HR payslip download is limited to finalized or paid payroll.
- Export actions are written to the activity log.
- Downloads use `Cache-Control: private, no-store, max-age=0`.

## Export Notes

The backend generates simple standards-compliant PDF files without public file persistence. CSV is generated in memory and streamed as a protected response.

## Tests

Feature tests cover:

- employee ownership and visible statuses;
- detailed earning/deduction items;
- authenticated employee PDF download;
- Admin/HR summary, CSV, and PDF export;
- Manager authorization denial; and
- rejection of draft administrative payslip download.
