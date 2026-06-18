# API MATRIX — Smart Attendance HRIS

> Phase 0 Audit — 2026-06-18

All endpoints are prefixed with `/api/v1`.

---

## Authentication

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| POST | `/login` | Public | AuthController | authService | LoginPage | ✅ |
| POST | `/logout` | Auth | AuthController | authService | — | — |
| GET | `/user` | Auth | AuthController | authService | — | — |

---

## Dashboard

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/dashboard/summary` | All | DashboardController | dashboardService | DashboardPage | — |
| GET | `/dashboard/employee-summary` | Admin/HR | DashboardController | dashboardService | DashboardPage | — |
| GET | `/dashboard/attendance-summary` | All | DashboardController | dashboardService | DashboardPage | — |
| GET | `/dashboard/leave-summary` | All | DashboardController | dashboardService | DashboardPage | — |

---

## Employees

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/employees` | Admin/HR | EmployeeController | employeeService | EmployeeListPage | — |
| POST | `/employees` | Admin/HR | EmployeeController | employeeService | EmployeeFormPage | — |
| GET | `/employees/{id}` | Admin/HR | EmployeeController | employeeService | EmployeeDetailPage | — |
| PUT | `/employees/{id}` | Admin/HR | EmployeeController | employeeService | EmployeeFormPage | — |
| DELETE | `/employees/{id}` | Admin | EmployeeController | employeeService | — | — |
| GET | `/employees/{id}/documents` | Admin/HR | EmployeeController | employeeService | EmployeeDetailPage | — |
| POST | `/employees/{id}/documents` | Admin/HR | EmployeeController | employeeService | EmployeeDetailPage | — |

---

## Departments

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/departments` | Admin/HR | DepartmentController | departmentService | DepartmentPage | — |
| POST | `/departments` | Admin | DepartmentController | departmentService | DepartmentPage | — |
| PUT | `/departments/{id}` | Admin | DepartmentController | departmentService | DepartmentPage | — |
| DELETE | `/departments/{id}` | Admin | DepartmentController | departmentService | DepartmentPage | — |

---

## Positions

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/positions` | Admin/HR | PositionController | positionService | PositionPage | — |
| POST | `/positions` | Admin | PositionController | positionService | PositionPage | — |
| PUT | `/positions/{id}` | Admin | PositionController | positionService | PositionPage | — |
| DELETE | `/positions/{id}` | Admin | PositionController | positionService | PositionPage | — |

---

## Branches

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/branches` | Admin/HR | BranchController | branchService | BranchPage | — |
| POST | `/branches` | Admin | BranchController | branchService | BranchPage | — |
| PUT | `/branches/{id}` | Admin | BranchController | branchService | BranchPage | — |
| DELETE | `/branches/{id}` | Admin | BranchController | branchService | BranchPage | — |

---

## Shifts

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/shifts` | Admin/HR | ShiftController | shiftService | ShiftPage | — |
| POST | `/shifts` | Admin/HR | ShiftController | shiftService | ShiftFormModal | — |
| PUT | `/shifts/{id}` | Admin/HR | ShiftController | shiftService | ShiftFormModal | — |
| DELETE | `/shifts/{id}` | Admin | ShiftController | shiftService | — | — |

---

## Shift Schedules

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/shift-schedules` | All | ShiftScheduleController | shiftScheduleService | ShiftSchedulePage | — |
| POST | `/shift-schedules` | Admin/HR | ShiftScheduleController | shiftScheduleService | ShiftSchedulePage | — |
| POST | `/shift-schedules/bulk` | Admin/HR | ShiftScheduleController | shiftScheduleService | ShiftSchedulePage | — |

---

## Attendance

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| POST | `/attendance/check-in` | Employee | AttendanceController | attendanceService | AttendancePage | — |
| POST | `/attendance/check-out` | Employee | AttendanceController | attendanceService | AttendancePage | — |
| POST | `/attendance/qr-check-in` | Employee | AttendanceController | attendanceService | AttendancePage | — |
| GET | `/attendance/history` | All | AttendanceController | attendanceService | AttendancePage | — |
| GET | `/attendance/monitoring` | Admin/HR | AttendanceController | attendanceService | AttendanceMonitoringPage | — |

---

## Attendance Correction — ⚠️ BROKEN (Sprint 1 target)

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test | Status |
|---|---|---|---|---|---|---|---|
| GET | `/attendance/correction-requests` | Admin/HR/Mgr | AttendanceCorrectionController | correctionService | CorrectionPage(reviewer) | ⚠️ | **BROKEN** — service `list()` missing |
| GET | `/attendance/my-correction-requests` | Employee | AttendanceCorrectionController | correctionService | CorrectionPage(employee) | ⚠️ | **BROKEN** — service `list()` missing |
| POST | `/attendance/correction-requests` | Employee | AttendanceCorrectionController | correctionService | CorrectionFormPage | ⚠️ | **BROKEN** — method name mismatch |
| GET | `/attendance/correction-requests/{id}` | Auth | AttendanceCorrectionController | correctionService | CorrectionDetailPage | ⚠️ | **PARTIAL** — no transform |
| POST | `/attendance/correction-requests/{id}/approve` | Admin/HR/Mgr | AttendanceCorrectionController | correctionService | CorrectionDetailPage | ⚠️ | DONE |
| POST | `/attendance/correction-requests/{id}/reject` | Admin/HR/Mgr | AttendanceCorrectionController | correctionService | CorrectionDetailPage | ⚠️ | DONE |
| POST | `/attendance/correction-requests/{id}/cancel` | Employee | AttendanceCorrectionController | correctionService | CorrectionDetailPage | ⚠️ | DONE |
| POST | `/attendance/manual-correction` | Admin/HR | AttendanceCorrectionController | ❌ MISSING | ❌ MISSING | ⚠️ | **BROKEN** + no FE |
| GET | `/attendance/correction-requests/{id}/attachment` | Auth | AttendanceCorrectionController | correctionService | CorrectionDetailPage | — | DONE |

---

## Leaves

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| POST | `/leaves/request` | Employee | LeaveController | leaveService | LeaveFormPage | — |
| GET | `/leaves/history` | All | LeaveController | leaveService | LeaveHistoryPage | — |
| GET | `/leaves/pending` | Admin/HR/Mgr | LeaveController | leaveService | LeaveApprovalPage | — |
| POST | `/leaves/{id}/approve` | Admin/HR/Mgr | LeaveController | leaveService | LeaveApprovalPage | — |
| POST | `/leaves/{id}/reject` | Admin/HR/Mgr | LeaveController | leaveService | LeaveApprovalPage | — |
| POST | `/leaves/{id}/cancel` | Employee | LeaveController | leaveService | LeaveHistoryPage | — |
| GET | `/leaves/balance` | Employee | LeaveController | leaveService | LeaveBalancePage | — |

---

## Profile & ESS

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/profile` | Auth | ProfileController | profileService | ProfilePage | — |
| PUT | `/profile` | Auth | ProfileController | profileService | ProfilePage | — |
| PUT | `/profile/password` | Auth | ProfileController | profileService | ProfilePage | — |
| GET | `/profile/emergency-contacts` | Auth | ProfileController | profileService | ProfilePage | — |
| POST | `/profile/emergency-contacts` | Auth | ProfileController | profileService | ProfilePage | — |
| POST | `/profile/change-request` | Employee | ProfileChangeController | profileChangeService | ProfileChangePage | — |
| GET | `/profile/change-requests` | Admin/HR | ProfileChangeController | profileChangeService | ProfileChangeReviewPage | — |
| POST | `/profile/change-requests/{id}/approve` | Admin/HR | ProfileChangeController | profileChangeService | ProfileChangeReviewPage | — |
| POST | `/profile/change-requests/{id}/reject` | Admin/HR | ProfileChangeController | profileChangeService | ProfileChangeReviewPage | — |

---

## Reports

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/reports/attendance` | Admin/HR | ReportController | reportService | ReportPage | — |
| GET | `/reports/leave` | Admin/HR | ReportController | reportService | ReportPage | — |
| GET | `/reports/employee` | Admin/HR | ReportController | reportService | ReportPage | — |
| GET | `/reports/attendance/export` | Admin/HR | ReportController | reportService | ReportPage | — |
| GET | `/reports/leave/export` | Admin/HR | ReportController | reportService | ReportPage | — |

---

## Activity Logs (Audit)

| Method | Endpoint | Role | Controller | Frontend Service | Frontend Page | Test |
|---|---|---|---|---|---|---|
| GET | `/activity-logs` | Admin | ActivityLogController | ❌ MISSING | ❌ MISSING | — |
| GET | `/activity-logs/{id}` | Admin | ActivityLogController | ❌ MISSING | ❌ MISSING | — |

> **Note**: Backend endpoints exist but frontend has no audit log viewer yet. This is Sprint 2 target.

---

## Key Observations

1. **Attendance Correction** is the only module with confirmed backend bugs — the service layer has missing/mismatched methods that prevent list, submit, and manual correction from working.

2. **Activity Logs** — backend exists but no frontend UI to view them (Sprint 2).

3. **Frontend consistently uses** `correctionService`, `attendanceService`, `leaveService`, `profileService`, `profileChangeService`, `dashboardService`, `reportService`, `employeeService`, `departmentService`, `positionService`, `branchService`, `shiftService`, `shiftScheduleService`, `authService`.

4. **No frontend test files found** for any module — testing is a gap across the board.

5. **Backend tests** — only `AttendanceCorrectionTest.php` found (14 tests, likely failing due to service bugs). No tests for other modules.