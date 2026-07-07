# API Matrix — Smart Attendance HRIS Backend

> Last verified: 30 June 2026
> Base path: `/api/v1`  
> Route sources: `routes/api_v1.php` and `routes/payroll.php`

## Access

- **Public:** no authentication.
- **Auth:** any authenticated role.
- **Admin/HR:** payroll and administrative HR data.
- **Admin/HR/Manager:** reviewer or report access with backend scope enforcement.

## Core Endpoint Groups

| Group           | Endpoint Prefix                                                        | Access                       | Purpose                               |
| --------------- | ---------------------------------------------------------------------- | ---------------------------- | ------------------------------------- |
| Authentication  | `/auth`                                                                | Public/Auth                  | Login, current user, logout, password |
| Dashboard       | `/dashboard`                                                           | Auth                         | Role-based summaries                  |
| Organization    | `/departments`, `/positions`, `/branches`                              | Admin/HR; Manager read       | Master data                           |
| Employees       | `/employees`                                                           | Admin/HR                     | Employee and manager administration   |
| Profile         | `/profile`, `/profile-change-requests`                                 | Auth/Admin/HR                | Self-service and reviewed changes     |
| Documents       | `/documents`, `/employee-documents`                                    | Auth/Admin/HR                | Private employee documents            |
| Shifts          | `/shifts`                                                              | Admin/HR                     | Shift definitions                     |
| Shift Schedules | `/shift-schedules`                                                     | Admin/HR/Manager/Employee    | Calendar, bulk, copy, team, self      |
| Attendance      | `/attendance`                                                          | Auth; reviewer routes scoped | Check-in/out, QR, monitoring, export  |
| Corrections     | `/attendance-corrections`                                              | Auth; reviewer routes scoped | Correction workflow                   |
| Leave           | `/leaves`, `/leave-types`, `/admin/leave-*`                            | Auth/reviewer/Admin/HR       | Leave workflow and master data        |
| Overtime        | `/overtime-requests`, `/overtime-policies`, `/admin/overtime-policies` | Auth/reviewer/Admin/HR       | Overtime workflow                     |
| Reports         | `/reports`                                                             | Admin/HR/Manager             | Attendance, leave, employee reports   |
| Audit           | `/activity-logs`                                                       | Admin/HR                     | Activity log list and detail          |

## Payroll Foundation

All payroll endpoints require Admin or HR.

| Method               | Endpoint                                          | Purpose                                 |
| -------------------- | ------------------------------------------------- | --------------------------------------- |
| GET/POST             | `/admin/salary-components`                        | List or create components               |
| GET/PUT/PATCH/DELETE | `/admin/salary-components/{salaryComponent}`      | Component detail and maintenance        |
| GET/POST             | `/admin/employees/{employee}/salary-profiles`     | List or create employee salary profiles |
| GET/PUT/PATCH/DELETE | `/admin/salary-profiles/{salaryProfile}`          | Salary profile maintenance              |
| GET/POST             | `/admin/payroll-periods`                          | List or create periods                  |
| GET/PUT/PATCH/DELETE | `/admin/payroll-periods/{payrollPeriod}`          | Period maintenance                      |
| POST                 | `/admin/payroll-periods/{payrollPeriod}/generate` | Generate draft payroll                  |
| GET                  | `/admin/payrolls`                                 | Payroll list and filters                |
| GET                  | `/admin/payrolls/{payroll}`                       | Payroll detail and items                |
| POST                 | `/admin/payrolls/{payroll}/recalculate`           | Recalculate draft                       |
| POST                 | `/admin/payrolls/{payroll}/review`                | Move draft to reviewed                  |
| POST                 | `/admin/payrolls/{payroll}/finalize`              | Finalize reviewed payroll               |
| POST                 | `/admin/payrolls/{payroll}/paid`                  | Mark finalized payroll paid             |
| POST                 | `/admin/payrolls/{payroll}/cancel`                | Cancel with mandatory reason            |

## Shift Schedule Endpoints

| Method    | Endpoint                           | Access                    | Purpose               |
| --------- | ---------------------------------- | ------------------------- | --------------------- |
| GET       | `/shift-schedules`                 | Admin/HR/Manager (scoped) | List with filters     |
| POST      | `/shift-schedules`                 | Admin/HR                  | Create single         |
| GET       | `/shift-schedules/{shiftSchedule}` | Admin/HR/Manager/Owner    | Show                  |
| PUT/PATCH | `/shift-schedules/{shiftSchedule}` | Admin/HR                  | Update                |
| DELETE    | `/shift-schedules/{shiftSchedule}` | Admin/HR                  | Delete                |
| POST      | `/shift-schedules/bulk`            | Admin/HR                  | Bulk assign           |
| POST      | `/shift-schedules/copy-week`       | Admin/HR                  | Copy week             |
| GET       | `/shift-schedules/calendar`        | Auth (scoped)             | Calendar data         |
| GET       | `/shift-schedules/my-schedule`     | Auth (self)               | Employee own schedule |

## Integration Rules

- Backend authorization is authoritative.
- Payroll data is not exposed to Manager or Employee routes in this foundation.
- Recalculation is draft-only.
- Finalized and paid records cannot be edited directly.
- Frontend services must use these endpoint names and handle validation and conflict responses.
