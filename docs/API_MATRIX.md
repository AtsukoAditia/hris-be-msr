# API Matrix — Smart Attendance HRIS Backend

> Last verified: 8 July 2026  
> Base path: `/api/v1`  
> Route sources: `routes/api_v1.php`, `routes/payroll.php`, and `routes/payroll_reporting.php`

## Access

- **Public:** no authentication.
- **Auth:** any authenticated role.
- **Admin/HR:** payroll and administrative HR data.
- **Admin/HR/Manager:** reviewer, report, or team-scope schedule access with backend scope enforcement.
- **Owner:** authenticated employee can access only owned self-service resources.

## Core Endpoint Groups

| Group | Endpoint Prefix | Access | Purpose |
|---|---|---|---|
| Authentication | `/auth` | Public/Auth | Login, current user, logout, password |
| Dashboard | `/dashboard` | Auth | Role-based summaries |
| Organization | `/departments`, `/positions`, `/branches` | Admin/HR; Manager read | Master data |
| Employees | `/employees` | Admin/HR | Employee and manager administration |
| Profile | `/profile`, `/profile-change-requests` | Auth/Admin/HR | Self-service and reviewed changes |
| Documents | `/documents`, `/employee-documents` | Auth/Admin/HR | Private employee documents |
| Shifts | `/shifts` | Admin/HR | Shift definitions |
| Shift Schedules | `/shift-schedules` | Auth/Admin/HR/Manager/Owner | Calendar, CRUD, bulk, copy, rotating, team, self |
| Attendance | `/attendance` | Auth; reviewer routes scoped | Check-in/out, QR, monitoring, export |
| Corrections | `/attendance-corrections` | Auth; reviewer routes scoped | Correction workflow |
| Leave | `/leaves`, `/leave-types`, `/admin/leave-*` | Auth/reviewer/Admin/HR | Leave workflow and master data |
| Overtime | `/overtime-requests`, `/overtime-policies`, `/admin/overtime-policies` | Auth/reviewer/Admin/HR | Overtime workflow |
| Reports | `/reports` | Admin/HR/Manager | Attendance, leave, employee reports |
| Audit | `/activity-logs` | Admin/HR | Activity log list and detail |
| Payroll | `/admin/salary-*`, `/admin/payroll-*` | Admin/HR | Payroll setup, processing, reporting |
| Payslips | `/payslips` | Owner | Employee payslip history, detail, download |

## Payroll Foundation

All payroll administration endpoints require Admin or HR.

| Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | `/admin/salary-components` | List or create components |
| GET/PUT/PATCH/DELETE | `/admin/salary-components/{salaryComponent}` | Component detail and maintenance |
| GET/POST | `/admin/employees/{employee}/salary-profiles` | List or create employee salary profiles |
| GET/PUT/PATCH/DELETE | `/admin/salary-profiles/{salaryProfile}` | Salary profile maintenance |
| GET/POST | `/admin/payroll-periods` | List or create periods |
| GET/PUT/PATCH/DELETE | `/admin/payroll-periods/{payrollPeriod}` | Period maintenance |
| POST | `/admin/payroll-periods/{payrollPeriod}/generate` | Generate draft payroll |
| GET | `/admin/payrolls` | Payroll list and filters |
| GET | `/admin/payrolls/{payroll}` | Payroll detail and items |
| POST | `/admin/payrolls/{payroll}/recalculate` | Recalculate draft |
| POST | `/admin/payrolls/{payroll}/review` | Move draft to reviewed |
| POST | `/admin/payrolls/{payroll}/finalize` | Finalize reviewed payroll |
| POST | `/admin/payrolls/{payroll}/paid` | Mark finalized payroll paid |
| POST | `/admin/payrolls/{payroll}/cancel` | Cancel with mandatory reason |

## Payslip and Payroll Reporting

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/payslips` | Owner | Employee payslip history |
| GET | `/payslips/{payroll}` | Owner | Employee payslip detail |
| GET | `/payslips/{payroll}/download` | Owner | Employee PDF payslip download |
| GET | `/admin/payroll-reports/summary` | Admin/HR | Payroll report summary |
| GET | `/admin/payroll-reports/export` | Admin/HR | Payroll CSV/PDF export |
| GET | `/admin/payrolls/{payroll}/payslip/download` | Admin/HR | Admin/HR payslip download |

## Shift Schedule Endpoints

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/shift-schedules` | Admin/HR/Manager | List with employee, department, branch, shift, date, day-off filters |
| POST | `/shift-schedules` | Admin/HR/Manager | Create single schedule or day off |
| GET | `/shift-schedules/{shiftSchedule}` | Admin/HR/Manager/Owner | Show schedule |
| PUT/PATCH | `/shift-schedules/{shiftSchedule}` | Admin/HR/Manager scoped | Update shift, notes, or day-off status |
| DELETE | `/shift-schedules/{shiftSchedule}` | Admin/HR | Delete schedule |
| GET | `/shift-schedules/employee/{employee}` | Admin/HR/Manager | Legacy employee lookup, registered before resource route |
| GET | `/shift-schedules/date/{date}` | Admin/HR/Manager | Legacy date lookup, registered before resource route |
| POST | `/shift-schedules/bulk` | Admin/HR/Manager | Bulk assign multiple employees and dates |
| POST | `/shift-schedules/copy-week` | Admin/HR/Manager | Copy one week into another week |
| POST | `/shift-schedules/rotating` | Admin/HR/Manager | Generate rotating shift pattern |
| GET | `/shift-schedules/my-schedule` | Owner | Employee own weekly schedule |
| GET | `/shift-schedules/team-schedule` | Admin/HR/Manager | Manager/team schedule view |

## Integration Rules

- Backend authorization is authoritative.
- Frontend role visibility is not treated as authorization.
- Payroll administration is restricted to Admin and HR.
- Employee payslip access is ownership-scoped.
- Shift schedule custom routes must remain registered before the resource route to avoid `{shiftSchedule}` shadowing.
- Bulk, copy-week, and rotating shift schedule responses include both `data` and `created` arrays for client compatibility.
- Recalculation is draft-only.
- Finalized and paid payroll records cannot be edited directly.
- Frontend services must use these endpoint names and handle validation, conflict, and 207 partial-success responses.
