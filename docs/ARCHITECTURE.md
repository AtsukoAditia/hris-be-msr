# Backend Architecture — Smart Attendance HRIS

## Overview

`hris-be-msr` is a Laravel REST API that serves the React PWA in `hris-fe-msr`. The API is versioned under `/api/v1` and uses Laravel Sanctum for token authentication.

## Request Flow

```text
Client
  -> Route (/api/v1)
  -> auth:sanctum
  -> role/policy/ownership checks
  -> Form Request validation
  -> Controller
  -> Service/domain logic
  -> Eloquent model and transaction
  -> API response/resource
  -> Activity log middleware
```

Controllers should remain thin. Business rules, transaction boundaries, status transitions, and reusable calculations belong in service or domain classes.

## Main Layers

| Layer | Responsibility |
|---|---|
| Routes | API versioning, middleware groups, and controller binding |
| Middleware | Authentication, role restrictions, audit capture, and cross-cutting rules |
| Form Requests | Input validation and request-level authorization where appropriate |
| Controllers | Translate HTTP requests into domain operations and responses |
| Services | Business rules, workflows, calculations, transactions, and locking |
| Models | Persistence, relationships, casts, scopes, and lifecycle behavior |
| Resources/Transformers | Stable response representation |
| Jobs/Events | Deferred or event-driven work when introduced |
| Storage | Private employee documents and correction attachments; public assets only when safe |

## Major Domains

```text
Identity & Access
Organization
Employee & ESS
Documents
Shift & Schedule
Attendance
Attendance Correction
Leave
Overtime
Reporting
Activity Log
Payroll (next)
```

Each domain should own its validation, service logic, tests, authorization rules, and documentation.

## Authentication and Authorization

- Authentication uses Laravel Sanctum.
- Frontend route guards improve UX but do not replace backend authorization.
- Administrative routes use role middleware.
- Ownership checks protect employee self-service records.
- Manager access must be constrained to direct reports or an explicitly defined organization scope.
- Sensitive file downloads require an authenticated endpoint and authorization check.

## Data and Transaction Rules

Use database transactions for operations that update multiple records or change financial/approval state. Row locking should be considered for:

- Approval or rejection actions.
- Leave balance mutation.
- Overtime actual-minute recording.
- Payroll generation and finalization.
- Any status transition vulnerable to duplicate requests.

Use decimal database columns for currency and rates. Do not use floating-point arithmetic for payroll values.

## File Storage

- Employee documents and attendance-correction attachments are private.
- File metadata is stored separately from the binary file.
- Downloads are streamed through authorized endpoints.
- Public storage is reserved for non-sensitive assets such as a company logo.
- File type, size, and extension must be validated.

## Audit Strategy

The activity log records operational metadata while filtering sensitive content.

Required audit targets include:

- Authentication and role changes.
- Employee and profile changes.
- Document operations.
- Attendance corrections.
- Leave and overtime decisions.
- Payroll calculations and status transitions.
- System setting changes.

Password, token, secret, and binary payload values must never be stored in audit previews.

## API Contract Rules

- Use `/api/v1` for active endpoints.
- Prefer predictable resource names and HTTP verbs.
- Return consistent success, validation, forbidden, not-found, and conflict responses.
- Paginate potentially large list endpoints.
- Keep filter names stable across report and review endpoints.
- Update `docs/API_MATRIX.md` whenever a route changes.

## Payroll Architecture Direction

The first payroll implementation should separate:

```text
SalaryComponent
EmployeeSalaryProfile
PayrollPeriod
Payroll
PayrollItem
```

Calculation should produce immutable payroll items from explicit inputs. Finalized payroll must not silently change when attendance, leave, or overtime data is edited later.

Recommended flow:

```text
Open period
  -> collect inputs
  -> generate draft
  -> review/recalculate
  -> finalize
  -> publish payslip
  -> mark paid
```

Every calculation and status transition requires an audit trail.

## Deployment Boundary

The backend owns:

- Authentication and authorization.
- Business rules.
- Database integrity.
- Private file access.
- Calculation correctness.
- Audit records.

The frontend owns presentation and interaction but must not be trusted to enforce security or calculate authoritative payroll results.
