# Project Status — Smart Attendance HRIS Backend

> Last verified: 20 June 2026  
> Repository: `AtsukoAditia/hris-be-msr`  
> Main branch: `main`

## Status Legend

| Symbol | Meaning |
|---|---|
| ✅ | Completed and integrated |
| 🟡 | Available but still needs enhancement |
| 🔵 | Current development focus |
| ⬜ | Planned |

## Module Status

| Module | Backend | Frontend Integration | Status |
|---|:---:|:---:|---|
| API v1, Sanctum authentication, RBAC | ✅ | ✅ | Completed |
| Role-based dashboard | ✅ | ✅ | Completed |
| Department, position, and branch master data | ✅ | ✅ | Completed |
| Employee management and direct-manager relation | ✅ | ✅ | Completed |
| Employee profile and emergency contacts | ✅ | ✅ | Completed |
| Employee document management | ✅ | ✅ | Completed |
| Employee self-service and profile change approval | ✅ | ✅ | Completed |
| Shift management and basic schedule assignment | ✅ | ✅ | Completed |
| Attendance with GPS, photo, office radius, and QR | ✅ | ✅ | Completed |
| Attendance correction and manual correction | ✅ | ✅ | Completed |
| Activity log viewer | ✅ | ✅ | Completed |
| Leave request, approval, balance, and history | ✅ | ✅ | Completed |
| Leave type, policy, holiday, and balance administration | ✅ | ✅ | Completed |
| Attendance, leave, and employee reports with CSV export | ✅ | ✅ | Completed |
| Overtime policy and request workflow | ✅ | ✅ | Completed |
| Payroll foundation | ⬜ | ⬜ | Next milestone |

## Completed Backend Capabilities

### Identity and access

- Laravel Sanctum authentication.
- Roles: `admin`, `hr`, `manager`, and `employee`.
- Backend authorization remains the source of truth.
- Employee ownership and manager direct-report scopes.

### Organization and employee data

- Department, position, and branch CRUD.
- Employee CRUD, employment data, bank data, and manager relation.
- Employee profile, emergency contacts, and profile completion.
- Private employee document storage and authenticated download.
- Sensitive profile change request with Admin/HR review.

### Time management

- Shift CRUD, overnight shift, and late tolerance.
- Shift schedule CRUD and bulk assignment endpoint.
- Attendance check-in/check-out with GPS and compressed photo evidence.
- Radius validation and QR attendance.
- Attendance monitoring, export, and correction workflow.

### Leave and overtime

- Leave request, cancellation, approval, rejection, and balance tracking.
- Leave type, policy, holiday, and balance administration.
- Overtime policy CRUD.
- Overtime request, cancellation, approval, rejection, and actual-minute recording.

### Reporting and audit

- Attendance, leave, and employee reports.
- CSV export.
- Activity log with actor, endpoint, request/response preview, status, IP, and timestamp.
- Sensitive fields and binary upload payloads are filtered from audit data.

## Security Baseline

- All business endpoints use `auth:sanctum`.
- Role middleware protects administrative routes.
- Manager access is limited to authorized direct reports.
- Sensitive documents and attachments use private storage.
- Critical status changes use database transactions and row locking where required.
- Validation uses Form Requests.
- Lifecycle-sensitive records use soft delete where applicable.

## Testing and CI

The backend workflow is expected to run:

1. Composer validation and dependency installation.
2. MySQL service and migrations.
3. Laravel Pint checks.
4. Full Laravel/PHPUnit test suite.
5. Diagnostic artifact upload when a failure occurs.

Local verification:

```bash
composer test
vendor/bin/pint --test
```

## Current Focus

### Basic Payroll Foundation

Planned initial scope:

- Salary component master.
- Earning and deduction component types.
- Employee salary profile.
- Payroll periods and cutoff dates.
- Draft payroll calculation.
- Attendance, approved leave, and approved overtime input.
- Review, finalize, paid, and cancel lifecycle.
- Payslip and payroll report.
- Audit trail and automated tests.

## Known Gaps

- Payroll has not been implemented.
- Shift scheduling is functional but does not yet provide a full weekly/monthly calendar experience.
- Export is currently centered on CSV; Excel and PDF remain planned.
- Notification center and reusable multi-level approval engine remain planned.
- Production deployment and final operational documentation remain incomplete.

## Source of Truth

- Endpoint definitions: `routes/api_v1.php`
- Current repository summary: `README.md`
- Module inventory: `docs/MODULES.md`
- Implementation sequence: `docs/ROADMAP.md`
- Endpoint integration map: `docs/API_MATRIX.md`
