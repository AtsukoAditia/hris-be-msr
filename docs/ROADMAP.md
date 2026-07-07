# Roadmap — Smart Attendance HRIS

> Last updated: 30 June 2026  
> Backend and frontend roadmaps must remain synchronized.

## Phase 0 — Foundation and Core HRIS ✅

- [x] Laravel REST API and React PWA integration
- [x] Sanctum authentication and four-role RBAC
- [x] Dashboard and organization master data
- [x] Employee management, profile, contacts, documents, and self-service
- [x] Shift management and basic schedule assignment
- [x] Attendance with GPS, photo, radius, and QR
- [x] Attendance correction and manual correction
- [x] Leave request, approval, master data, and balance administration
- [x] Activity log viewer
- [x] Attendance, leave, and employee reports with CSV export
- [x] Overtime policy and request workflow

## Sprint 1 — Basic Payroll Foundation 🔵

### Backend ✅

- [x] Salary component master
- [x] Earning and deduction types
- [x] Fixed, percentage, and formula-ready calculation configuration
- [x] Effective-dated employee salary profile
- [x] Payroll period and cutoff dates
- [x] Payroll and payroll item records
- [x] Integer-safe currency calculation
- [x] Basic salary, attendance, absence, unpaid leave, and overtime inputs
- [x] Generate and recalculate draft payroll
- [x] Review, finalize, paid, and cancel workflow
- [x] Authorization and Form Request validation
- [x] Transactions, row locking, and audit records
- [x] Backend feature tests and green CI

### Frontend 🔵

- [ ] Payroll route and Admin/HR navigation
- [ ] Salary component administration
- [ ] Employee salary profile administration
- [ ] Payroll period administration
- [ ] Draft generation and recalculation
- [ ] Payroll list and detail breakdown
- [ ] Review, finalize, paid, and cancel actions
- [ ] Loading, error, empty, validation, and conflict states
- [ ] Responsive table/card layouts
- [ ] Component tests, lint, build, and mobile acceptance
- [ ] Frontend documentation

## Sprint 2 — Payslip and Payroll Reporting

- [ ] Employee payslip history and detail
- [ ] Authenticated payslip download
- [ ] Payroll summary by period
- [ ] Earning and deduction report breakdown
- [ ] CSV payroll export
- [ ] PDF payslip after the core workflow is stable
- [ ] Payroll report filters and regression tests

## Sprint 3 — Shift Schedule Calendar ✅

### Backend ✅

- [x] ShiftSchedule model with shift_id nullable, is_day_off, notes, schedule_date
- [x] Database migrations (create table + is_day_off + nullable shift_id)
- [x] ShiftScheduleService with bulkStore, copyWeek, conflict validation
- [x] ShiftScheduleController with index, store, show, update, destroy, bulkStore, copyWeek
- [x] ShiftSchedulePolicy (admin/hr/manager scope/employee self)
- [x] BulkStoreShiftScheduleRequest, CopyWeekRequest validation
- [x] ShiftScheduleResource, ShiftResource
- [x] ShiftScheduleFactory, ShiftScheduleSeeder
- [x] Calendar endpoint (GET /api/v1/shift-schedules/calendar)
- [x] My schedule endpoint (GET /api/v1/shift-schedules/my-schedule)
- [x] RotatingShiftScheduleRequest for pattern-based assignment
- [x] Backend feature tests (26 tests, 82 assertions for ShiftSchedule)
- [x] Full test suite: 415 tests, 1671 assertions all green

### Frontend ✅

- [x] Shift schedule service (CRUD, bulk, copy, calendar, my-schedule)
- [x] ShiftSchedulePage with weekly/monthly calendar toggle
- [x] Day cell click to create assignment
- [x] Bulk assignment modal (multi-employee, date range, shift, rotating)
- [x] Copy previous week modal
- [x] Filters: department, branch, search
- [x] Manager team view (auto-scoped)
- [x] MySchedulePage for employee personal schedule
- [x] Routes for /shift-schedule and /my-schedule
- [x] Sidebar navigation (admin: "Jadwal Shift", employee: "Jadwal Saya")
- [x] ESLint zero warnings
- [x] Production build success

## Sprint 4 — Notification Center

- [ ] Inbox and unread count
- [ ] Mark read and mark all read
- [ ] Deep links and preferences
- [ ] Queue-based delivery with local fallback
- [ ] Leave, correction, profile, overtime, schedule, document, and checkout triggers

## Sprint 5 — Generic Approval Workflow

- [ ] Approval request, step, and action models
- [ ] Multi-level approval and delegation
- [ ] Configurable approval flows
- [ ] Incremental adapters for existing workflows
- [ ] Backward-compatible rollout and regression tests

## Sprint 6 — Attendance and Leave Enhancements

- [ ] Attendance anomaly and missing checkout
- [ ] Early leave, work from home, and business trip
- [ ] Half-day leave and hourly permission
- [ ] Carry forward, expiration, and team leave calendar

## Sprint 7 — Reporting Enhancement

- [ ] Excel and PDF exports
- [ ] Attendance anomaly, late, overtime, expiry, and turnover reports
- [ ] Consistent filters and background exports

## Sprint 8 — System Settings

- [ ] Company profile and logo
- [ ] Timezone, date format, working days, and weekends
- [ ] Attendance, leave, overtime, and payroll defaults
- [ ] Upload limits, QR expiration, and notification preferences

## Sprint 9 — Optional HRIS Expansion

- [ ] Organization chart
- [ ] Recruitment
- [ ] Onboarding and offboarding
- [ ] Performance management
- [ ] Reimbursement and asset management
- [ ] Announcements, training, and employee loans

## Sprint 10 — Production Hardening

- [ ] Production environment, HTTPS, CORS, and secrets
- [ ] Queue, scheduler, backups, monitoring, and health checks
- [ ] Database indexing and deployment workflow
- [ ] PWA install, mobile acceptance, and accessibility
- [ ] ERD, API docs, manuals, limitations, and changelog

## Definition of Done

A module is complete only when backend and frontend contracts are synchronized, authorization and validation are enforced, critical operations are transaction-safe, automated tests and CI pass, mobile behavior is accepted, and documentation is current in both repositories.
