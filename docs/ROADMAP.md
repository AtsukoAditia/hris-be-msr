# Roadmap — Smart Attendance HRIS

> Last updated: 20 June 2026  
> This roadmap is shared conceptually with `hris-fe-msr` and must remain synchronized.

## Roadmap Principles

1. Complete end-to-end modules, not isolated backend endpoints.
2. Keep backend authorization as the source of truth.
3. Require tests, CI, responsive frontend behavior, and documentation before a module is marked complete.
4. Introduce database changes with safe migrations and backward-compatible rollout when existing data is affected.
5. Prioritize HRIS core functions before optional business modules.

## Phase 0 — Foundation and Core HRIS ✅

- [x] Laravel REST API under `/api/v1`
- [x] React PWA integration
- [x] Sanctum authentication
- [x] Admin, HR, Manager, and Employee roles
- [x] Role-based dashboard
- [x] Department, position, and branch master data
- [x] Employee management and direct-manager relation
- [x] Employee profile and emergency contacts
- [x] Employee document management
- [x] Employee self-service and profile change approval
- [x] Shift management and basic shift schedule
- [x] Attendance with GPS, photo, office radius, and QR
- [x] Attendance correction and manual correction
- [x] Leave request, approval, balance, and history
- [x] Leave type, policy, holiday, and balance administration
- [x] Activity log viewer
- [x] Attendance, leave, and employee reports
- [x] CSV export
- [x] Overtime policy and request workflow

---

## Sprint 1 — Basic Payroll Foundation 🔵

### Database and domain

- [ ] Salary component master
- [ ] Component category: earning or deduction
- [ ] Fixed, percentage, and formula-ready calculation type
- [ ] Employee salary profile
- [ ] Payroll period and cutoff dates
- [ ] Payroll, payroll item, and payroll status models
- [ ] Safe decimal and currency handling

### Calculation inputs

- [ ] Basic salary
- [ ] Fixed allowances and deductions
- [ ] Attendance summary
- [ ] Late and absence inputs
- [ ] Approved unpaid leave input
- [ ] Approved overtime and actual-minute input

### Workflow

- [ ] Generate draft payroll
- [ ] Recalculate draft payroll
- [ ] Review payroll
- [ ] Finalize payroll
- [ ] Mark payroll as paid
- [ ] Cancel payroll with reason
- [ ] Prevent changes after finalization without controlled rollback

### Delivery requirements

- [ ] Role authorization
- [ ] Form Request validation
- [ ] Transaction and locking strategy
- [ ] Audit trail
- [ ] Backend feature tests
- [ ] Frontend integration
- [ ] Responsive payroll workspace
- [ ] Documentation update

---

## Sprint 2 — Payslip and Payroll Reporting

- [ ] Employee payslip view
- [ ] Authenticated payslip download
- [ ] Payroll history per employee
- [ ] Payroll summary by period
- [ ] Earning and deduction breakdown
- [ ] CSV payroll export
- [ ] PDF payslip after the core workflow is stable
- [ ] Payroll report filters
- [ ] Payroll regression tests

---

## Sprint 3 — Shift Schedule Calendar

- [ ] Weekly calendar
- [ ] Monthly calendar
- [ ] Employee, department, and branch filters
- [ ] Bulk assignment UI
- [ ] Copy previous week
- [ ] Period schedule generation
- [ ] Rotating shift templates
- [ ] Day-off assignment
- [ ] Conflict validation
- [ ] Manager team schedule
- [ ] Employee personal schedule improvement

---

## Sprint 4 — Notification Center

- [ ] In-app notifications
- [ ] Unread count
- [ ] Mark as read and mark all as read
- [ ] Deep link to related records
- [ ] Notification preferences
- [ ] Queue-based delivery with local fallback
- [ ] Triggers for leave, correction, profile change, overtime, schedule changes, expiring documents, and missing checkout
- [ ] Tests

---

## Sprint 5 — Generic Approval Workflow

- [ ] Approval request model
- [ ] Approval steps and actions
- [ ] Multi-level approval
- [ ] Approver delegation
- [ ] Configurable approval flow
- [ ] Adapter migration for leave, correction, profile changes, and overtime
- [ ] Backward-compatible rollout
- [ ] Regression tests

---

## Sprint 6 — Attendance and Leave Enhancements

### Attendance

- [ ] Attendance anomaly detection
- [ ] Missing checkout reminder
- [ ] Early-leave calculation
- [ ] Work-from-home and business-trip attendance status
- [ ] Branch-specific attendance configuration

### Leave

- [ ] Half-day leave
- [ ] Hourly permission
- [ ] Cuti bersama
- [ ] Carry forward and expiration
- [ ] Team leave calendar
- [ ] Team availability warning
- [ ] Attachment requirement by leave type

---

## Sprint 7 — Reporting Enhancement

- [ ] Excel export
- [ ] PDF export
- [ ] Attendance anomaly report
- [ ] Late and overtime report
- [ ] Contract and document expiry report
- [ ] Employee turnover report
- [ ] Consistent cross-report filters
- [ ] Background export for large datasets

---

## Sprint 8 — System Settings

- [ ] Company profile and logo
- [ ] Timezone and date format
- [ ] Working days and weekends
- [ ] Attendance grace period
- [ ] Overtime defaults
- [ ] Leave defaults
- [ ] Payroll cutoff
- [ ] Upload limits
- [ ] QR expiration
- [ ] Notification preferences

---

## Sprint 9 — Optional HRIS Expansion

- [ ] Organization chart
- [ ] Recruitment
- [ ] Onboarding and offboarding
- [ ] Performance management
- [ ] Reimbursement
- [ ] Asset management
- [ ] Announcement and company calendar
- [ ] Training and development
- [ ] Employee loan

These modules are intentionally deferred until payroll and the core operational flow are stable.

---

## Sprint 10 — Production Hardening and Final Documentation

- [ ] Production environment and secrets management
- [ ] HTTPS and production CORS
- [ ] Rate limiting and security headers
- [ ] Queue, scheduler, and backup strategy
- [ ] Structured logging and error monitoring
- [ ] Health endpoint
- [ ] Database indexing review
- [ ] CI/CD deployment workflow
- [ ] PWA install and mobile acceptance
- [ ] Accessibility review
- [ ] ERD and architecture diagrams
- [ ] API documentation
- [ ] User and administrator manual
- [ ] Known limitations and changelog

## Definition of Done

A module is complete only when:

- Database and domain model are stable.
- API contract and frontend integration are synchronized.
- Authorization and ownership rules are enforced by the backend.
- Validation, error, loading, and empty states are handled.
- Critical operations use transactions where appropriate.
- Automated tests pass.
- CI is green.
- Mobile behavior is accepted.
- Documentation is updated in both repositories.
