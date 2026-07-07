# Project Status — Smart Attendance HRIS Backend

> Last verified: 8 July 2026  
> Repository: `AtsukoAditia/hris-be-msr`  
> Main branch: `main`  
> Paired frontend: `AtsukoAditia/hris-fe-msr`

## Executive Summary

The backend is now an operational HRIS API with completed and frontend-synchronized modules for core HR, attendance, leave, overtime, reports, activity logs, payroll foundation, payslips, payroll reporting, and Sprint 3 shift schedule.

The next product direction is to move from operational CRUD/workflow coverage into advanced HRIS capability:

1. Payroll Pro Engine.
2. Workforce and attendance intelligence.
3. Advanced shift scheduling.
4. Policy engine for leave and overtime.
5. Employee lifecycle.
6. Performance and analytics.

## Module Status Matrix

| Module | Backend | Frontend Contract | Status |
|---|:---:|:---:|---|
| Authentication, RBAC, and Dashboard | ✅ | ✅ | Completed |
| Organization and Employee Management | ✅ | ✅ | Completed |
| Profile, Documents, and Self-Service | ✅ | ✅ | Completed |
| Shift, Attendance, and Correction | ✅ | ✅ | Completed |
| Leave and Overtime | ✅ | ✅ | Completed |
| Reports and Activity Log | ✅ | ✅ | Completed |
| Basic Payroll Foundation | ✅ | ✅ | Completed |
| Payslip and Payroll Reporting | ✅ | ✅ | Completed |
| Sprint 3 Shift Schedule Self-Service and Team View | ✅ | ✅ | Completed |

## Completed Capability Details

### Core HRIS

- Sanctum authentication, logout, current-user lookup, and password change.
- Role-based access for Admin, HR, Manager, and Employee.
- Department, position, and branch master data.
- Employee management and direct-manager relation.
- Profile self-service and reviewed profile change requests.
- Employee document self-service and Admin/HR document management.

### Attendance and Workforce Operations

- GPS/photo/radius/QR attendance check-in/out.
- Attendance history and monitoring.
- Attendance correction with request, cancel, attachment, approval, rejection, and manual correction.
- Shift master data.
- Shift schedule CRUD, day off, bulk assign, copy-week, rotating pattern, self schedule, and manager team view.

### Leave and Overtime

- Leave types, leave policies, holidays, and leave balances.
- Leave request, cancellation, approval, rejection, and balance lookup.
- Overtime policy and employee overtime request flow.
- Overtime approval/rejection and actual-minute recording.

### Payroll Foundation

- Salary component master with earning and deduction types.
- Fixed, percentage, and formula-ready calculation configuration.
- Effective-dated employee salary profile and component assignment.
- Payroll period and cutoff management.
- Payroll generation from salary, attendance, absence, unpaid leave, and approved overtime actual minutes.
- Payroll item breakdown and calculation snapshots.
- Draft recalculation.
- Review, finalize, paid, and cancel lifecycle.
- Transactions, row locks, audit records, and Admin/HR-only authorization.

### Payslip and Reporting

- Employee payslip history and detail.
- Authenticated employee payslip PDF download.
- Admin/HR payroll report summary.
- Payroll CSV/PDF export.
- Admin/HR payslip download for finalized or paid payroll records.
- Ownership and status authorization for payslip access.

## Current Known Limitations

These are not defects; they are the next planned product increments:

- No automatic tax/social-security calculation yet.
- No post-finalization payroll adjustment ledger yet.
- No multi-level payroll approval yet.
- No shift swap workflow yet.
- No advanced shift conflict/coverage engine yet.
- No notification center yet.
- No attendance anomaly scoring yet.
- No employee lifecycle module yet.
- No performance management module yet.

## Best Next Feature Priorities

### Priority 1 — Payroll Pro Engine

Goal: make payroll safe enough for production-grade HR/Finance operations.

Backend scope:

- Payroll adjustment ledger for finalized records.
- Adjustment types: earning correction, deduction correction, bonus, penalty, retroactive correction.
- Multi-level payroll approval: HR prepare, Finance review, Director approve, paid.
- Configurable tax/social-security rule model and calculation snapshots.
- Payroll lock period and audit trail per sensitive field.
- Payroll simulation before final generation.
- THR/bonus/special run support.
- Bank disbursement export.

### Priority 2 — Advanced Shift Scheduling

Goal: convert shift scheduling into workforce management.

Backend scope:

- Shift conflict detector.
- Minimum rest-hour validation.
- Maximum weekly work-hour validation.
- Shift coverage requirement per branch/department/day/shift.
- Schedule publish/unpublish state.
- Shift change log and schedule versioning.
- Shift swap request, approval, rejection, and cancellation.
- Team capacity and under-staffing report endpoints.

### Priority 3 — Attendance Intelligence

Goal: detect attendance risk automatically instead of only storing attendance records.

Backend scope:

- Late/early leave pattern detector.
- Missing checkout and repeated correction detector.
- GPS outlier and suspicious attendance marker.
- Monthly attendance health score.
- Employee/team attendance risk summary.
- Rule-based warning events that can feed notification center.

### Priority 4 — Policy Engine for Leave and Overtime

Goal: make approval decisions rule-aware.

Backend scope:

- Leave accrual schedules.
- Carry-forward expiry.
- Blackout date restrictions.
- Team leave capacity check before approval.
- Overtime budget limits by department/branch.
- Overtime fatigue rule.
- Planned-vs-actual overtime comparison.

### Priority 5 — Employee Lifecycle

Goal: cover employee lifecycle beyond attendance/payroll.

Backend scope:

- Onboarding checklist.
- Probation tracking.
- Contract end reminders.
- Promotion/mutation history.
- Offboarding checklist.
- Asset handover tracking.
- Document expiry reminders.
- Employee timeline events.

### Priority 6 — Performance and HR Analytics

Goal: move from operational HRIS to decision-support platform.

Backend scope:

- KPI/OKR cycles.
- Self-review, manager review, and 360 feedback.
- Review calibration and rating history.
- Link performance result to bonus/payroll inputs.
- Executive dashboards for headcount, turnover, absenteeism, overtime cost, payroll cost, leave liability, and shift coverage.

## Engineering Standards for Next Work

- Keep API contracts documented in `docs/API_MATRIX.md`.
- Keep module status synchronized with `AtsukoAditia/hris-fe-msr` docs.
- Every new write operation needs authorization, validation, tests, and audit consideration.
- Every sensitive workflow needs transaction safety.
- Add migrations with safe defaults and reversible down migrations where practical.
- Add feature tests before marking a module complete.
- Avoid breaking existing frontend contracts; add backward-compatible fields when required.

## Source of Truth

- Routes: `routes/api_v1.php`, `routes/payroll.php`, `routes/payroll_reporting.php`.
- Module inventory: `docs/MODULES.md`.
- API contract: `docs/API_MATRIX.md`.
- Roadmap: `docs/ROADMAP.md`.
- CLINE instructions: `CLINE.md`.
