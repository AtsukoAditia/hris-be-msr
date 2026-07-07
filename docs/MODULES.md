# Module Inventory — Smart Attendance HRIS Backend

> Last updated: 8 July 2026  
> Repository: `AtsukoAditia/hris-be-msr`  
> Main branch: `main`

## Current Backend Module Status

| Module | Main Capabilities | Access | Backend Status | Frontend Contract |
|---|---|---|---|---|
| Authentication and RBAC | Sanctum login/logout/me, password change, role middleware, ownership scope | Public/Auth | ✅ Complete | ✅ Synced |
| Dashboard | Role-based summary data | All roles | ✅ Complete | ✅ Synced |
| Organization Master | Departments, positions, branches | Admin/HR; Manager read | ✅ Complete | ✅ Synced |
| Employee Management | Employee CRUD, manager relation, profile linkage, face enrollment support | Admin/HR | ✅ Complete | ✅ Synced |
| Profile Self-Service | Employee profile, emergency contacts, change requests | Auth/Admin/HR review | ✅ Complete | ✅ Synced |
| Documents | Employee private documents, categories, self-service and admin document flows | Auth/Admin/HR | ✅ Complete | ✅ Synced |
| Shift Master | Shift definitions and administration | Admin/HR | ✅ Complete | ✅ Synced |
| Attendance | GPS/photo/radius/QR check-in/out, history, monitoring, export | Auth; reviewer scope | ✅ Complete | ✅ Synced |
| Attendance Correction | Request, cancel, attachment, review, manual correction | Auth; reviewer/admin scope | ✅ Complete | ✅ Synced |
| Leave | Request, balance, approval, leave type, policy, holiday, admin balance | Auth; reviewer/admin scope | ✅ Complete | ✅ Synced |
| Overtime | Policy, request, approval/reject, cancel, actual minutes | Auth; reviewer/admin scope | ✅ Complete | ✅ Synced |
| Reports | Attendance, leave, employee reports, CSV export | Admin/HR/Manager | ✅ Complete | ✅ Synced |
| Activity Log | Actor, action, endpoint, status, IP, timestamp | Admin/HR | ✅ Complete | ✅ Synced |
| Payroll Foundation | Salary components, salary profiles, periods, payroll generation, lifecycle | Admin/HR | ✅ Complete | ✅ Synced |
| Payslip and Payroll Reporting | Employee payslip history/detail/download, admin summary/export/download | Employee owner; Admin/HR | ✅ Complete | ✅ Synced |
| Sprint 3 Shift Schedule | CRUD, day off, bulk, copy-week, rotating, self schedule, team schedule | Owner/Admin/HR/Manager | ✅ Complete | ✅ Synced |

## Payroll Foundation

| Submodule | Main Capabilities | Access | Status |
|---|---|---|---|
| Salary Components | Earning/deduction; fixed, percentage, formula-ready calculation type | Admin/HR | ✅ Complete |
| Employee Salary Profile | Effective dates, base salary, currency, notes, component assignments | Admin/HR | ✅ Complete |
| Payroll Period | Period dates, cutoff dates, open/closed state | Admin/HR | ✅ Complete |
| Payroll Calculation | Basic salary, attendance, absence, unpaid leave, overtime actual minutes | Admin/HR | ✅ Complete |
| Payroll Items | Snapshot breakdown for earnings/deductions and calculation traceability | Admin/HR | ✅ Complete |
| Payroll Workflow | Generate, recalculate draft, review, finalize, paid, cancel | Admin/HR | ✅ Complete |
| Payroll Reports | Summary, filter, CSV/PDF export, admin payslip download | Admin/HR | ✅ Complete |
| Employee Payslips | Finalized/paid history, detail, authenticated PDF download | Owner | ✅ Complete |

## Shift Schedule Foundation

| Submodule | Main Capabilities | Access | Status |
|---|---|---|---|
| Schedule CRUD | Create, list, show, update, delete schedules | Admin/HR; scoped Manager | ✅ Complete |
| Day Off | Nullable `shift_id`, `is_day_off`, notes | Admin/HR; scoped Manager | ✅ Complete |
| Bulk Assign | Multi-employee, multi-date assignment | Admin/HR; scoped Manager contract exists | ✅ Complete |
| Copy Week | Copy schedules from source week to target week with optional employee filter | Admin/HR; scoped Manager contract exists | ✅ Complete |
| Rotating Pattern | Generate multi-week rotating pattern from shift sequence | Admin/HR; scoped Manager contract exists | ✅ Complete |
| My Schedule | Employee-owned weekly schedule endpoint | Owner | ✅ Complete |
| Team Schedule | Manager/admin/HR team schedule endpoint | Admin/HR/Manager | ✅ Complete |
| Route Hardening | Custom lookup routes before resource route | Admin/HR/Manager | ✅ Complete |

## Current Scope Limitations

These are intentionally not implemented yet and must be treated as future roadmap, not bugs:

- Automatic tax/social-security calculation.
- Post-finalization payroll adjustment ledger.
- Multi-level payroll approval.
- Advanced shift schedule conflict and coverage analytics.
- Shift swap request and approval workflow.
- Notification center and preference management.
- Attendance anomaly intelligence.
- Employee lifecycle and performance management.

## Integration Rules

- Backend authorization is the source of truth; frontend role guards are only UX controls.
- All business endpoints must remain under `/api/v1`.
- Use Form Request validation for write endpoints.
- Critical payroll transitions must use transactions and row locks.
- Employee payslip access must be ownership-scoped and status-scoped to finalized/paid records.
- Shift schedule custom routes must stay before `apiResource('/shift-schedules', ...)` to avoid `{shiftSchedule}` shadowing.
- Bulk/copy/rotating shift responses should keep both `data` and `created` arrays for frontend compatibility.

## Deferred Optional Modules

Organization chart, recruitment, onboarding/offboarding, performance management, reimbursement, asset management, announcements, training, and employee loans are deferred until stabilization and payroll/workforce intelligence are stable.
