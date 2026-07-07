# Roadmap — Smart Attendance HRIS Backend

> Last updated: 8 July 2026  
> Repository: `AtsukoAditia/hris-be-msr`  
> Keep synchronized with frontend roadmap in `AtsukoAditia/hris-fe-msr`.

## Completed Phases

| Phase | Status | Backend Scope |
|---|---|---|
| Phase 0 — Core HRIS | ✅ Complete | Auth/RBAC, dashboard, organization, employee, profile, documents, attendance, correction, leave, overtime, reports, audit log |
| Sprint 1 — Payroll Foundation | ✅ Complete | Salary components, salary profiles, payroll periods, payroll generation, lifecycle, payroll items, audit-safe transitions |
| Sprint 2 — Payslip and Payroll Reporting | ✅ Complete | Employee payslip history/detail/download, admin summary/export/download |
| Sprint 3 — Shift Schedule | ✅ Complete | Schedule CRUD, day off, bulk assign, copy-week, rotating pattern, my schedule, team schedule |

## Next Sprint Roadmap

| Sprint | Priority | Status | Backend Goal |
|---|---:|---|---|
| Sprint 4 — Stabilization and Contract QA | P0 | 🔵 Next | Confirm route contracts, tests, docs, seed data, and indexes after FE/BE merge |
| Sprint 5 — Payroll Pro Engine | P0 | Planned | Add adjustment ledger, multi-step approval, configurable calculation rules, simulation, lock period, and audit expansion |
| Sprint 6 — Advanced Shift Scheduling | P1 | Planned | Add conflict rules, rest-hour rules, work-hour rules, coverage requirements, publish state, versioning, and shift swap workflow |
| Sprint 7 — Attendance Intelligence | P1 | Planned | Add late/early patterns, missing checkout detection, repeated correction detection, risk score, and monthly health summary |
| Sprint 8 — Leave and Overtime Policy Engine | P1 | Planned | Add accrual, carry-forward expiry, blackout dates, team capacity checks, overtime limits, and planned-vs-actual comparison |
| Sprint 9 — Notification Center | P2 | Planned | Add inbox, unread count, read actions, preferences, deep links, workflow event producers, and delivery adapter boundary |
| Sprint 10 — Employee Lifecycle | P2 | Planned | Add onboarding, probation, contract reminders, promotion/mutation history, offboarding, asset handover, document expiry, timeline |
| Sprint 11 — Performance and HR Analytics | P3 | Planned | Add KPI/OKR cycles, reviews, feedback, rating history, and executive analytics endpoints |
| Sprint 12 — Production Hardening | P0 ongoing | Planned | Add deployment readiness, queues, scheduler, backup, monitoring, health checks, indexing, manuals, and changelog |

## Sprint 4 Acceptance Criteria

- Backend test suite and Pint pass.
- Critical frontend route contracts have backend smoke tests.
- Shift schedule manager team view has regression coverage.
- Bulk/copy/rotating responses keep `created` and `data` compatibility.
- Demo seed data supports payroll, payslip, shift schedule, leave, overtime, documents, and attendance.
- `docs/API_MATRIX.md`, `docs/MODULES.md`, `docs/PROJECT_STATUS.md`, and frontend docs remain synchronized.

## Top 3 Product Priorities

1. Payroll Pro Engine.
2. Advanced Shift Scheduling with swap and coverage.
3. Attendance Intelligence and HR analytics.

## Global Definition of Done

A module is complete only when backend and frontend contracts are synchronized, authorization and validation are enforced, critical operations are transaction-safe, tests and CI pass, paired frontend behavior is accepted, documentation is current in both repositories, and CLINE guidance remains accurate.
