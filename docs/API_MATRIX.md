# API Matrix — Smart Attendance HRIS Backend

> Last verified: 20 June 2026  
> Base path: `/api/v1`  
> Authoritative route file: `routes/api_v1.php`

## Access Legend

| Access | Meaning |
|---|---|
| Public | No authentication required |
| Auth | Any authenticated role |
| Admin/HR | Administrative HR access |
| Admin/HR/Manager | Review or reporting access with manager scope enforced by backend |

## Authentication and Dashboard

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| POST | `/auth/login` | Public | Login and issue Sanctum token |
| GET | `/auth/me` | Auth | Current authenticated user |
| POST | `/auth/logout` | Auth | Revoke active token |
| POST | `/auth/change-password` | Auth | Change account password |
| GET | `/dashboard/summary` | Auth | Role-based dashboard summary |

## Organization Master Data

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/departments` | Admin/HR/Manager | Department list |
| GET | `/departments/{department}` | Admin/HR/Manager | Department detail |
| POST | `/departments` | Admin/HR | Create department |
| PUT/PATCH | `/departments/{department}` | Admin/HR | Update department |
| DELETE | `/departments/{department}` | Admin/HR | Delete or deactivate department according to business rules |
| GET | `/positions` | Admin/HR/Manager | Position list |
| GET | `/positions/{position}` | Admin/HR/Manager | Position detail |
| POST | `/positions` | Admin/HR | Create position |
| PUT/PATCH | `/positions/{position}` | Admin/HR | Update position |
| DELETE | `/positions/{position}` | Admin/HR | Delete or deactivate position |
| GET | `/branches` | Admin/HR/Manager | Branch list |
| GET | `/branches/{branch}` | Admin/HR/Manager | Branch detail |
| POST | `/branches` | Admin/HR | Create branch |
| PUT/PATCH | `/branches/{branch}` | Admin/HR | Update branch |
| DELETE | `/branches/{branch}` | Admin/HR | Delete or deactivate branch |

## Employee Management

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/employees` | Admin/HR | Employee list and filters |
| POST | `/employees` | Admin/HR | Create employee |
| GET | `/employees/{employee}` | Admin/HR | Employee detail |
| PUT/PATCH | `/employees/{employee}` | Admin/HR | Update employee |
| DELETE | `/employees/{employee}` | Admin/HR | Delete or deactivate employee |
| GET | `/employees/manager-options` | Admin/HR | Direct-manager options |
| POST | `/employees/{employee}/face-enrollment` | Admin/HR | Store face enrollment image |

## Profile and Emergency Contacts

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/profile/me` | Auth | Current employee profile |
| PUT/PATCH | `/profile/me` | Auth | Update self-service fields |
| GET | `/profile/me/emergency-contacts` | Auth | Own emergency contacts |
| POST | `/profile/me/emergency-contacts` | Auth | Add emergency contact |
| PUT/PATCH | `/profile/me/emergency-contacts/{emergencyContact}` | Auth | Update own emergency contact |
| DELETE | `/profile/me/emergency-contacts/{emergencyContact}` | Auth | Remove own emergency contact |
| GET | `/employees/{employee}/profile` | Admin/HR | Managed employee profile |
| PUT/PATCH | `/employees/{employee}/profile` | Admin/HR | Update managed employee profile |
| GET/POST | `/employees/{employee}/emergency-contacts` | Admin/HR | List or add employee contact |
| PUT/PATCH/DELETE | `/employees/{employee}/emergency-contacts/{emergencyContact}` | Admin/HR | Maintain employee contact |

## Profile Change Requests

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/profile/change-requests` | Auth | Own request history; reviewer route is role-scoped |
| POST | `/profile/change-requests` | Auth | Submit sensitive data change |
| GET | `/profile/change-requests/{profileChangeRequest}` | Auth | Request detail with ownership or reviewer authorization |
| DELETE | `/profile/change-requests/{profileChangeRequest}` | Auth | Cancel pending own request |
| POST | `/profile-change-requests/{profileChangeRequest}/approve` | Admin/HR | Approve change request |
| POST | `/profile-change-requests/{profileChangeRequest}/reject` | Admin/HR | Reject change request |

## Employee Documents

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/document-categories` | Auth | Document category options |
| GET | `/documents/my` | Auth | Own document list |
| GET | `/documents/my/summary` | Auth | Own document summary |
| GET | `/documents/my/{employeeDocument}` | Auth | Own document metadata |
| GET | `/documents/my/{employeeDocument}/download` | Auth | Authenticated own-document download |
| GET | `/employee-documents` | Admin/HR | Global employee document list |
| GET | `/employee-documents/summary` | Admin/HR | Document summary |
| GET/POST | `/employees/{employee}/documents` | Admin/HR | List or upload employee document |
| GET/PUT/PATCH/DELETE | `/employees/{employee}/documents/{employeeDocument}` | Admin/HR | View, update, or delete document metadata |
| POST | `/employees/{employee}/documents/{employeeDocument}/replace` | Admin/HR | Replace file and increment version |
| GET | `/employees/{employee}/documents/{employeeDocument}/download` | Admin/HR | Authenticated document download |

## Shift and Schedule

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| API Resource | `/shifts` | Admin/HR | Shift CRUD |
| API Resource | `/shift-schedules` | Admin/HR | Shift schedule CRUD |
| GET | `/shift-schedules/employee/{employeeId}` | Admin/HR | Schedule by employee |
| GET | `/shift-schedules/date/{date}` | Admin/HR | Schedule by date |
| POST | `/shift-schedules/bulk` | Admin/HR | Bulk shift assignment |

## Attendance

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/attendance/my` | Auth | Own attendance history |
| GET | `/attendance/today` | Auth | Own attendance today |
| POST | `/attendance/check-in` | Auth | GPS and photo check-in |
| POST | `/attendance/check-out` | Auth | GPS and photo check-out |
| POST | `/attendance/check-in/qr` | Auth | QR check-in |
| POST | `/attendance/check-out/qr` | Auth | QR check-out |
| GET | `/attendance` | Admin/HR/Manager | Attendance monitoring with role scope |
| GET | `/attendance/export` | Admin/HR/Manager | Attendance export |
| GET | `/attendance/employee/{employeeId}` | Admin/HR/Manager | Employee attendance with scope enforcement |
| GET | `/attendance/{attendance}` | Admin/HR/Manager | Attendance detail |
| GET | `/attendance/settings` | Admin/HR/Manager | Attendance and office-radius settings |
| PUT | `/attendance/settings` | Admin/HR | Update attendance settings |
| POST | `/attendance/qr/generate` | Admin/HR | Generate expiring QR payload |

## Attendance Correction

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/attendance-corrections/my` | Auth | Own correction requests |
| POST | `/attendance-corrections` | Auth | Submit correction request |
| GET | `/attendance-corrections/{correction}` | Auth | Authorized correction detail |
| POST | `/attendance-corrections/{correction}/cancel` | Auth | Cancel pending own request |
| GET | `/attendance-corrections/{correction}/attachment` | Auth | Authenticated attachment download |
| GET | `/attendance-corrections` | Admin/HR/Manager | Reviewer list with scope |
| POST | `/attendance-corrections/{correction}/approve` | Admin/HR/Manager | Approve correction |
| POST | `/attendance-corrections/{correction}/reject` | Admin/HR/Manager | Reject correction |
| POST | `/attendance-corrections/manual` | Admin/HR | Manual attendance correction |

## Leave

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/leave-types` | Auth | Active leave type options |
| GET | `/leave-types/{leaveType}` | Auth | Leave type detail |
| GET | `/leaves/my` | Auth | Own leave history |
| GET | `/leaves/balance` | Auth | Own leave balances |
| POST | `/leaves` | Auth | Submit leave request |
| GET | `/leaves/{leave}` | Auth | Authorized leave detail |
| DELETE | `/leaves/{leave}` | Auth | Cancel pending own leave |
| GET | `/leaves` | Admin/HR/Manager | Reviewer leave list |
| POST | `/leaves/{leave}/approve` | Admin/HR/Manager | Approve leave |
| POST | `/leaves/{leave}/reject` | Admin/HR/Manager | Reject leave |

## Leave Administration

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| API Resource | `/admin/leave-types` | Admin/HR | Leave type CRUD |
| API Resource | `/admin/leave-policies` | Admin/HR | Leave policy CRUD |
| API Resource | `/admin/holidays` | Admin/HR | Holiday CRUD |
| GET/POST/PUT/PATCH/DELETE | `/admin/leave-balances` | Admin/HR | Leave balance administration |
| POST | `/admin/leave-balances/adjust` | Admin/HR | Audited balance adjustment |

## Overtime

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/overtime-policies` | Auth | Active overtime policy options |
| GET | `/overtime-requests/my` | Auth | Own overtime requests |
| POST | `/overtime-requests` | Auth | Submit overtime request |
| GET | `/overtime-requests/{overtimeRequest}` | Auth | Authorized request detail |
| POST | `/overtime-requests/{overtimeRequest}/cancel` | Auth | Cancel pending own request |
| GET | `/overtime-requests` | Admin/HR/Manager | Reviewer list with manager scope |
| POST | `/overtime-requests/{overtimeRequest}/approve` | Admin/HR/Manager | Approve overtime |
| POST | `/overtime-requests/{overtimeRequest}/reject` | Admin/HR/Manager | Reject overtime |
| POST | `/overtime-requests/{overtimeRequest}/record-actual` | Admin/HR | Record actual approved minutes |
| API Resource | `/admin/overtime-policies` | Admin/HR | Overtime policy CRUD |

## Reporting and Audit

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/reports/attendance` | Admin/HR/Manager | Attendance report |
| GET | `/reports/leave` | Admin/HR/Manager | Leave report |
| GET | `/reports/employee` | Admin/HR/Manager | Employee report |
| GET | `/reports/export` | Admin/HR/Manager | CSV export selected by report type |
| GET | `/activity-logs` | Admin/HR | Activity log list and filters |
| GET | `/activity-logs/{activityLog}` | Admin/HR | Activity log detail |

## Integration Notes

- Frontend services must use the endpoint names in this document and `routes/api_v1.php`; older endpoint aliases are not authoritative.
- Role restrictions shown here describe route-level access. Controllers, policies, and services must still enforce ownership and manager scope.
- File downloads require authentication and must not be replaced with public storage URLs.
- New endpoints must update this matrix, the frontend route/service documentation, and automated tests in the same milestone.
