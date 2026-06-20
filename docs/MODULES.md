# Module Inventory — Smart Attendance HRIS

> Last updated: 20 June 2026

## Completed Core Modules

| Module | Main Capabilities | Access |
|---|---|---|
| Authentication and RBAC | Sanctum, Admin, HR, Manager, Employee, ownership and manager scope | All roles |
| Dashboard | Role-based summaries | All roles |
| Organization Master | Department, position, branch | Admin, HR; Manager read |
| Employee Management | Employee records, direct manager, profile, contacts, documents | Admin, HR; employee self-service |
| Shift and Schedule | Shift CRUD and basic assignments | Admin, HR |
| Attendance | GPS, photo, radius, QR, history, monitoring | All roles; reviewer scope |
| Attendance Correction | Request, review, attachment, manual correction | All roles; reviewer scope |
| Leave | Request, approval, type, policy, holiday, balance | All roles; reviewer/admin scope |
| Overtime | Policy, request, approval, actual minutes | All roles; reviewer/admin scope |
| Activity Log | Actor, action, endpoint, status, IP, timestamp | Admin, HR |
| Reports | Attendance, leave, employee, CSV export | Admin, HR, Manager |

## Payroll Foundation

| Module | Main Capabilities | Access | Status |
|---|---|---|---|
| Salary Components | Earning/deduction; fixed, percentage, formula-ready | Admin, HR | Backend Complete |
| Employee Salary Profile | Effective dates and component assignments | Admin, HR | Backend Complete |
| Payroll Period | Period, cutoff, open/closed state | Admin, HR | Backend Complete |
| Payroll Calculation | Salary, attendance, absence, unpaid leave, overtime | Admin, HR | Backend Complete |
| Payroll Workflow | Generate, recalculate, review, finalize, paid, cancel | Admin, HR | Backend Complete |
| Payroll Workspace | Responsive administration UI | Admin, HR | Frontend In Progress |
| Payslip | Employee breakdown and history | Employee, Admin, HR | Planned |
| Payroll Report | Period summary and CSV/PDF | Admin, HR | Planned |

## Payroll Foundation Rules

- One payroll record per employee and period.
- Generation requires an open period.
- Recalculation is limited to draft payroll.
- Status flow is `draft → reviewed → finalized → paid`.
- Cancellation requires a reason and is blocked after payment.
- Finalized salary data cannot be silently changed.
- Critical transitions use database transactions and row locking.

## Planned Enhancements

- Payslip and payroll reporting.
- Weekly/monthly shift calendar.
- Notification center.
- Generic approval workflow.
- Attendance and leave enhancements.
- Excel/PDF and background reporting.
- System settings.
- Production hardening and final documentation.

## Optional Modules

Organization chart, recruitment, onboarding/offboarding, performance, reimbursement, assets, announcements, training, and employee loans remain deferred until core payroll and production readiness are complete.
