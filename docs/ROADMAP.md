# ROADMAP — Smart Attendance HRIS

> Phase 0 Audit — 2026-06-18

---

## PHASE 0 — AUDIT AND BASELINE ✅

- [x] Audit backend structure
- [x] Audit frontend structure
- [x] Identify Attendance Correction bugs
- [x] Create PROJECT_STATUS.md
- [x] Create ROADMAP.md
- [x] Create API_MATRIX.md

---

## SPRINT 1 — ATTENDANCE CORRECTION (END-TO-END)

### Backend

- [ ] Fix `AttendanceCorrectionService::list()` — add missing method
- [ ] Fix `AttendanceCorrectionService::submit()` — align controller↔service call
- [ ] Fix `AttendanceCorrectionService::manualCorrection()` — fix signature mismatch
- [ ] Fix `create()` field name: `attendance_date` → `correction_date`
- [ ] Fix attendance column names: `check_in_time`→`check_in`, `check_out_time`→`check_out`, `attendance_date`→`date`
- [ ] Add `list()` method with pagination, filters (status, employee_id, date range, department)
- [ ] Add `transform()` usage in controller `show()`
- [ ] Add API Resource class for consistent response
- [ ] Add employee and department filter params to reviewer list
- [ ] Ensure `cancel()` signature aligns with controller
- [ ] Fix test if needed
- [ ] Run `php artisan test` and verify passing

### Frontend

- [ ] Fix correction form — remove raw attendance_id, use date picker + auto-lookup
- [ ] Add employee/department filter to reviewer list page
- [ ] Add manual correction form for Admin/HR
- [ ] Ensure loading/error/empty states on all correction pages
- [ ] Ensure mobile responsive layout
- [ ] Run lint and build

### Documentation

- [ ] Update PROJECT_STATUS.md after Sprint 1
- [ ] Update API_MATRIX.md with final endpoints
- [ ] Update ROADMAP.md checkboxes

---

## SPRINT 2 — AUDIT LOG VIEWER

- [ ] Audit log list endpoint (Admin only)
- [ ] Audit log detail endpoint
- [ ] Filter: actor, module, action, date range, HTTP status
- [ ] Search by endpoint/record
- [ ] Pagination
- [ ] Old/new value comparison
- [ ] CSV export
- [ ] Sensitive value hiding
- [ ] Frontend: audit log table with filters
- [ ] Frontend: detail modal/page with JSON diff
- [ ] Tests

---

## SPRINT 3 — LEAVE MASTER AND POLICY

- [ ] Leave type master CRUD
- [ ] Leave policy configuration
- [ ] Leave quota per type
- [ ] Paid/unpaid flag
- [ ] Requires attachment flag
- [ ] Max consecutive days
- [ ] Min service months
- [ ] Gender restriction
- [ ] Carry-forward configuration
- [ ] Holiday master CRUD
- [ ] Working-day calculation
- [ ] Leave balance transaction/history
- [ ] Safe data migration for existing leave records
- [ ] Tests

---

## SPRINT 4 — LEAVE ENHANCEMENT

- [ ] Half-day leave
- [ ] Hourly permission
- [ ] Cuti bersama
- [ ] Leave balance adjustment by HR
- [ ] Balance transaction history
- [ ] Carry forward + expiration
- [ ] Team leave calendar
- [ ] Overlap/team availability warning
- [ ] Leave report enhancement
- [ ] Tests + mobile UI

---

## SPRINT 5 — SHIFT SCHEDULE CALENDAR

- [ ] Weekly/monthly calendar view
- [ ] Employee/department/branch filter
- [ ] Bulk assignment UI
- [ ] Copy previous week
- [ ] Generate schedule period
- [ ] Rotating shift
- [ ] Day off
- [ ] Schedule template
- [ ] Conflict validation
- [ ] Edit assignment
- [ ] Team schedule for manager
- [ ] Employee personal schedule
- [ ] Tests

---

## SPRINT 6 — NOTIFICATION CENTER

- [ ] In-app notification system
- [ ] Notification inbox + unread count
- [ ] Mark as read / mark all as read
- [ ] Notification preference
- [ ] Deep link to related record
- [ ] Triggers: leave, correction, profile change, shift, expiring docs, missing checkout
- [ ] Queue with local fallback
- [ ] Tests

---

## SPRINT 7 — GENERIC APPROVAL WORKFLOW

- [ ] Approval request/step/action models
- [ ] Multi-level approval
- [ ] Delegation
- [ ] Incremental migration: correction → leave → profile change → overtime → reimbursement
- [ ] Adapter pattern for backward compatibility
- [ ] Regression tests before full migration

---

## SPRINT 8 — OVERTIME MANAGEMENT

- [ ] Overtime request (date, planned start/end, reason)
- [ ] Employee request + Manager/HR approval
- [ ] Actual attendance comparison
- [ ] Approved minutes
- [ ] Overtime status
- [ ] Overtime report
- [ ] Payroll-ready output
- [ ] Tests

---

## SPRINT 9 — BASIC PAYROLL FOUNDATION

- [ ] Salary component master
- [ ] Earning/deduction type
- [ ] Employee salary profile
- [ ] Payroll period/cutoff
- [ ] Payroll calculation + items
- [ ] Attendance/OT input
- [ ] Draft → Review → Finalize → Paid → Cancel
- [ ] Payslip + employee history
- [ ] Payroll report + CSV
- [ ] Audit trail
- [ ] Tests

---

## SPRINT 10 — SYSTEM SETTINGS

- [ ] Company profile/logo
- [ ] Timezone, date format
- [ ] Working days/weekend
- [ ] Attendance grace period
- [ ] Overtime rules
- [ ] Leave defaults
- [ ] Payroll cutoff
- [ ] Upload limits
- [ ] QR expiration
- [ ] Notification preferences
- [ ] Tests

---

## SPRINT 11 — REPORTING ENHANCEMENT

- [ ] Excel/PDF export
- [ ] Contract/document expiry report
- [ ] Attendance anomaly report
- [ ] Late/overtime report
- [ ] Employee turnover
- [ ] Consistent filters
- [ ] Background export for large data

---

## SPRINT 12 — ORGANIZATION CHART

- [ ] Hierarchy tree from direct_manager
- [ ] Department grouping
- [ ] Expand/collapse
- [ ] Employee summary
- [ ] Cycle protection
- [ ] Authorization for sensitive data

---

## SPRINT 13 — RECRUITMENT

- [ ] Job vacancy CRUD
- [ ] Candidate + application
- [ ] Interview schedule + evaluation
- [ ] Hiring decision pipeline
- [ ] Convert hired → employee

---

## SPRINT 14 — ONBOARDING & OFFBOARDING

- [ ] Onboarding checklist + assignment
- [ ] Document collection
- [ ] Asset assignment
- [ ] Offboarding: resignation, handover, asset return, exit interview
- [ ] Account revocation

---

## SPRINT 15 — PERFORMANCE MANAGEMENT

- [ ] Performance period + KPI
- [ ] Self/manager assessment
- [ ] Score + feedback
- [ ] Finalization + history

---

## SPRINT 16 — REIMBURSEMENT

- [ ] Expense claim + type
- [ ] Receipt upload
- [ ] Manager + finance approval
- [ ] Payment status
- [ ] Report + audit trail

---

## SPRINT 17 — ASSET MANAGEMENT

- [ ] Asset category + CRUD
- [ ] Assignment + return
- [ ] Repair/maintenance
- [ ] Asset history
- [ ] Offboarding integration

---

## SPRINT 18 — ANNOUNCEMENT & COMPANY CALENDAR

- [ ] Announcement CRUD + target roles/dept/branch
- [ ] Publish/expiration dates
- [ ] Read confirmation
- [ ] Company calendar: holidays, events, training

---

## SPRINT 19 — TRAINING & DEVELOPMENT

- [ ] Training catalog + session
- [ ] Enrollment + attendance + result
- [ ] Certificate + expiration
- [ ] Skill matrix
- [ ] Employee training history

---

## SPRINT 20 — EMPLOYEE LOAN

- [ ] Loan request + type + approval
- [ ] Installment schedule
- [ ] Payroll deduction integration
- [ ] Settlement + report

---

## SPRINT 21 — PRODUCTION HARDENING

- [ ] HTTPS, CORS, rate limiting
- [ ] Queue/scheduler/backup
- [ ] Error monitoring + structured logging
- [ ] Health endpoint + cache strategy
- [ ] Deployment workflow + secrets management
- [ ] Security headers, DB indexing
- [ ] PWA + offline + mobile acceptance
- [ ] Accessibility review

---

## SPRINT 22 — FINAL DOCUMENTATION

- [ ] Installation/dev/deployment guide
- [ ] ERD + architecture + role matrix
- [ ] API documentation
- [ ] User/admin manual + screenshots
- [ ] Known limitations + changelog