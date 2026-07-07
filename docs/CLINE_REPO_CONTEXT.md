# CLINE Repo Context — Backend

> Last updated: 8 July 2026  
> Repository: `AtsukoAditia/hris-be-msr`  
> Workspace pairing: parent folder `HRIS/` contains both `hris-be-msr/` and `hris-fe-msr/`.

## Role of This Repository

This repository is the Laravel backend API for Smart Attendance HRIS. It is the source of truth for authorization, validation, business rules, database persistence, and workflow transitions.

Paired frontend repository: `../hris-fe-msr`.

## Current Completed Scope

- Authentication and role access.
- Dashboard.
- Organization and employee management.
- Profile self-service and profile change review.
- Documents.
- Shift master.
- Attendance and attendance correction.
- Leave and overtime.
- Reports and activity log.
- Payroll foundation.
- Payslip and payroll reporting.
- Sprint 3 shift schedule: CRUD, day off, bulk assign, copy-week, rotating pattern, my schedule, team schedule.

## Important Route Sources

- `routes/api.php`
- `routes/api_v1.php`
- `routes/payroll.php`
- `routes/payroll_reporting.php`

## Important Docs

- `README.md`
- `docs/API_MATRIX.md`
- `docs/MODULES.md`
- `docs/PROJECT_STATUS.md`
- `docs/ROADMAP.md`
- `docs/PAYSLIP_REPORTING.md`

## Backend Development Rules for CLINE

- Keep all business endpoints under `/api/v1`.
- Backend authorization is authoritative; frontend role guards are UX only.
- Use Form Request validation for write endpoints.
- Use policies or scoped queries for owner and manager/team access.
- Use transactions for critical payroll, approval, schedule, leave, overtime, and adjustment flows.
- Preserve backward-compatible response shapes when frontend already depends on them.
- Keep shift schedule custom routes before the resource route to avoid route shadowing.
- Update docs whenever adding routes, services, modules, or workflow states.
- Add feature tests before marking backend work complete.

## Current Next Sprint

Sprint 4 — Stabilization and Contract QA.

Tasks:

- Run backend tests and Pint.
- Add smoke tests for critical frontend contracts.
- Add regression coverage for manager team schedule.
- Add regression coverage for shift schedule `created` and `data` response compatibility.
- Review indexes and demo seed data.
- Keep backend and frontend docs synchronized.

## Advanced Roadmap Priority

1. Payroll Pro Engine.
2. Advanced Shift Scheduling.
3. Attendance Intelligence.
4. Leave and Overtime Policy Engine.
5. Notification Center.
6. Employee Lifecycle.
7. Performance Management and HR Analytics.
8. Production Hardening.

## Definition of Done

A backend change is done only when:

- API contract is clear.
- Authorization is enforced.
- Validation is enforced.
- Tests pass.
- Critical workflow is transaction-safe where needed.
- Frontend contract is checked in `../hris-fe-msr`.
- Docs are updated.
