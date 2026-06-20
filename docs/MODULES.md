# Module Inventory — Smart Attendance HRIS

> Last updated: 20 June 2026

## Status Legend

| Status | Meaning |
|---|---|
| Completed | Backend, frontend integration, authorization, and core workflow are available |
| Enhancement | Core feature exists but advanced UX or rules remain planned |
| Next | Current milestone |
| Planned | Not yet implemented |

## Core Platform

| Module | Main Capabilities | Primary Access | Status |
|---|---|---|---|
| Authentication | Login, logout, current user, change password | All roles | Completed |
| RBAC | Admin, HR, Manager, Employee; ownership and manager scope | All roles | Completed |
| Dashboard | Role-based employee, attendance, leave, and shift summaries | All roles | Completed |
| Activity Log | Actor, endpoint, action, request/response preview, status, IP, timestamp | Admin, HR | Completed |

## Organization and Employee

| Module | Main Capabilities | Primary Access | Status |
|---|---|---|---|
| Department | CRUD and active organization master data | Admin, HR; read for Manager | Completed |
| Position | CRUD and department-related position master | Admin, HR; read for Manager | Completed |
| Branch | CRUD, office location, attendance coordinates, and radius metadata | Admin, HR; read for Manager | Completed |
| Employee Management | CRUD, employment data, bank data, face enrollment, direct manager | Admin, HR | Completed |
| Employee Profile | Personal and employment profile, profile completion | Employee self-service; Admin, HR management | Completed |
| Emergency Contacts | Employee-owned and HR-managed contact records | Employee, Admin, HR | Completed |
| Employee Documents | Private upload, metadata, version, replacement, expiry, authenticated download | Employee, Admin, HR | Completed |
| Profile Change Approval | Sensitive profile changes with request, review, approve, reject, cancel | Employee, Admin, HR | Completed |

## Time and Attendance

| Module | Main Capabilities | Primary Access | Status |
|---|---|---|---|
| Shift | Regular/overnight shift, tolerance, active status | Admin, HR | Completed |
| Shift Schedule | CRUD, employee/date lookup, bulk assignment | Admin, HR | Enhancement |
| Attendance | GPS/photo check-in and check-out, history, monitoring | All roles; reviewer scope | Completed |
| Attendance Radius | Branch/office coordinate validation | All roles; setting by Admin/HR | Completed |
| QR Attendance | Expiring QR generation and validated check-in/check-out | All roles; generation by Admin/HR | Completed |
| Attendance Correction | Request, attachment, cancel, review, approve/reject, manual correction | All roles; reviewer scope | Completed |

## Leave and Overtime

| Module | Main Capabilities | Primary Access | Status |
|---|---|---|---|
| Leave Request | Request, balance, history, detail, cancel | All roles | Completed |
| Leave Approval | Review, approve, reject, manager scope | Admin, HR, Manager | Completed |
| Leave Type | Configurable leave category | Admin, HR; options for all roles | Completed |
| Leave Policy | Quota and policy configuration | Admin, HR | Completed |
| Holiday | Holiday master for working-day calculation | Admin, HR | Completed |
| Leave Balance Administration | Balance CRUD, adjustment, and transaction handling | Admin, HR | Completed |
| Overtime Policy | Active policy options and Admin/HR CRUD | All roles; CRUD by Admin/HR | Completed |
| Overtime Request | Request, cancel, review, approve/reject, actual minutes | All roles; reviewer scope | Completed |

## Reporting

| Module | Main Capabilities | Primary Access | Status |
|---|---|---|---|
| Attendance Report | Filters, summaries, and export | Admin, HR, Manager | Completed |
| Leave Report | Filters, status summary, and export | Admin, HR, Manager | Completed |
| Employee Report | Employee and organization filters | Admin, HR, Manager | Completed |
| CSV Export | Report and attendance export | Authorized reviewer roles | Completed |
| Excel/PDF Export | Spreadsheet and printable export | Authorized reviewer roles | Planned |

## Payroll

| Module | Main Capabilities | Primary Access | Status |
|---|---|---|---|
| Salary Components | Earning/deduction component master | Admin, HR | Next |
| Employee Salary Profile | Employee-specific salary composition | Admin, HR | Next |
| Payroll Period | Period, cutoff, and processing status | Admin, HR | Next |
| Payroll Calculation | Attendance, leave, overtime, earning, and deduction inputs | Admin, HR | Next |
| Payroll Approval | Draft, review, finalize, paid, cancel | Admin, HR | Planned |
| Payslip | Employee payroll breakdown and history | Employee, Admin, HR | Planned |
| Payroll Report | Period summary and export | Admin, HR | Planned |

## Planned Platform Enhancements

| Module | Scope | Status |
|---|---|---|
| Notification Center | In-app inbox, unread state, deep links, preferences | Planned |
| Generic Approval Workflow | Reusable multi-level approval and delegation | Planned |
| System Settings | Company, workday, attendance, leave, payroll, upload, QR settings | Planned |
| Advanced Shift Calendar | Weekly/monthly calendar, rotation, copy period, conflict detection | Planned |
| Attendance Anomaly | Missing checkout, early leave, duplicate and unusual attendance detection | Planned |
| Advanced Leave | Half-day, hourly permission, carry forward, team calendar | Planned |
| Advanced Reporting | Excel/PDF, anomaly, turnover, expiry, background export | Planned |

## Optional Business Modules

- Organization chart
- Recruitment
- Onboarding and offboarding
- Performance management
- Reimbursement
- Asset management
- Announcement and company calendar
- Training and development
- Employee loan

Optional modules must not block payroll, production hardening, or the completion of the core HRIS workflow.
