# PROJECT_STATUS.md

## Recent Completed Work

### Sprint 1: Employee Data Management ✅ DONE

- Employee CRUD (admin)
- Department & Branch management
- Employee profile & document management
- Employee self-service profile viewing/editing

### Sprint 2: Attendance & Correction ✅ DONE

- Attendance check-in/check-out (GPS + selfie)
- Attendance correction request workflow (employee submit, manager approve/reject)
- Manual attendance correction by HR/Admin
- Attendance reporting

### Sprint 2.5: Leave & Overtime Management ✅ DONE

- Leave management (types, policies, balances, request/approve/reject/cancel)
- Overtime management (request, approval, actual recording)
- Admin leave type & policy management
- Holiday management
- Payroll periods & basic payroll generation

### Sprint 3: Shift Schedule Calendar ✅ DONE

**Backend:**

- `ShiftSchedule` model with `is_day_off`, `notes`, soft deletes
- `ShiftSchedulePolicy` — admin/hr full, manager team, employee own
- `ShiftScheduleService` — bulk assign, copy week, rotating shifts, conflict detection
- `ShiftScheduleController` — full REST + bulk/copy/rotating endpoints
- Form Requests: `StoreShiftScheduleRequest`, `BulkStoreShiftScheduleRequest`, `CopyWeekRequest`, `RotatingShiftScheduleRequest`
- `ShiftScheduleResource` — consistent response shape
- Migrations: `add_is_day_off_to_shift_schedules_table`, `make_shift_id_nullable_on_shift_schedules_table`
- Conflict validation: duplicate schedule detection per employee per date
- 26 feature tests passing

**Frontend:**

- `ShiftSchedulePage` — weekly calendar grid with admin actions
- `MySchedulePage` — employee personal schedule (filtered, read-only)
- `shiftScheduleService` — API layer for all schedule endpoints
- Routes: `/shift-schedule` (admin/hr/manager), `/my-schedule` (all roles)
- Sidebar navigation updated

**Endpoints:**
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/shift-schedules` | All roles | List (filtered by role scope) |
| POST | `/api/v1/shift-schedules` | Admin/HR | Create single schedule |
| GET | `/api/v1/shift-schedules/{id}` | All roles | Show (scope-checked) |
| PUT | `/api/v1/shift-schedules/{id}` | Admin/HR | Update |
| DELETE | `/api/v1/shift-schedules/{id}` | Admin/HR | Delete |
| POST | `/api/v1/shift-schedules/bulk` | Admin/HR | Bulk assign |
| POST | `/api/v1/shift-schedules/copy-week` | Admin/HR | Copy week schedule |
| POST | `/api/v1/shift-schedules/rotating` | Admin/HR | Rotating shift pattern |

**Authorization:**

- Admin/HR: full CRUD on all schedules
- Manager: view team schedules, no write
- Employee: view own schedule only

**Known Limitations:**

- Single-day view not yet implemented (week view only)
- No drag-and-drop calendar
- No shift swap requests
- No automatic conflict resolution for overlapping shifts
