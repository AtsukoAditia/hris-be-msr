# HRIS Backend — hris-be-msr

> **Smart Attendance HRIS** — Laravel REST API  
> Backend untuk sistem HRIS berbasis role yang menangani employee management, shift, attendance dengan GPS/foto/radius/QR, leave, approval, dan report.

## Project Overview

Project ini dikembangkan sebagai portfolio HRIS berbasis REST API yang terintegrasi dengan frontend React PWA. Fokus pengembangan saat ini adalah memperluas aplikasi dari sistem attendance menjadi HRIS yang lebih lengkap, terstruktur, aman, dan siap didemonstrasikan.

- **API base path:** `/api/v1`
- **Authentication:** Laravel Sanctum
- **Roles:** Admin, HR, Manager, Employee
- **Pair repository:** [hris-fe-msr](https://github.com/AtsukoAditia/hris-fe-msr)
- **Master roadmap:** [Issue #1](https://github.com/AtsukoAditia/hris-be-msr/issues/1)

---

## Current Status

| Module | Status | Description |
|---|:---:|---|
| Foundation & API v1 | ✅ | Struktur API, response JSON, integrasi frontend |
| Authentication & Role Access | ✅ | Login, logout, auth profile, change password, role middleware |
| Dashboard Summary | ✅ | Ringkasan operasional dan personal berdasarkan role |
| Employee Management | ✅ | CRUD, filter, user mapping, soft delete, face enrollment |
| Shift Management | ✅ | CRUD, active/inactive, overnight shift, late tolerance |
| Basic Shift Schedule | ✅ | Assignment shift per employee dan tanggal |
| Attendance | ✅ | Check-in/out, GPS, foto, late dan overtime calculation |
| Radius Attendance | ✅ | Validasi jarak dari lokasi kantor |
| QR Attendance | ✅ | Generate, scan, expiry, type validation, radius fallback |
| Leave Request & Balance | ✅ | Pengajuan, saldo, overlap validation, cancellation |
| Leave Approval | ✅ | Approve, reject, history, approver information |
| Reports & CSV Export | ✅ | Attendance, leave, employee report dan CSV |
| Organization Master Data | 🔵 In Progress | Department, position, branch, manager relation |
| Employee Profile & Documents | ⬜ Planned | Profil lengkap, emergency contact, dokumen |
| Attendance Correction | ⬜ Planned | Request koreksi dan approval |
| Generic Approval Workflow | ⬜ Planned | Approval reusable lintas modul |
| Audit Log & Notification | ⬜ Planned | Aktivitas sensitif dan notifikasi in-app |
| Payroll | ⬜ Planned | Salary component, payroll period, payslip |
| Testing & Deployment | ⬜ Planned | Automated tests, documentation, production deployment |

### Current Focus — Organization Master Data

Issue implementasi: [Implement organization master data API](https://github.com/AtsukoAditia/hris-be-msr/issues/2)

Target awal:

1. Department master data.
2. Position master data.
3. Branch atau work location master data.
4. Relasi employee ke department, position, branch, dan manager.
5. Migrasi bertahap dari kolom `department` dan `position` berbentuk string.
6. CRUD API, validation, authorization, seeder, dan feature test.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP ^8.3 |
| Authentication | Laravel Sanctum API Token |
| Authorization | Custom Role Middleware |
| Database | MySQL 8 |
| ORM | Eloquent |
| Storage | Laravel public disk |
| File Upload | Face enrollment dan attendance evidence |
| API Format | REST JSON API |

---

## Completed Features

### Authentication & Authorization

- Login dengan Sanctum token.
- Logout dan token revocation.
- Authenticated user profile melalui `/auth/me`.
- Change password.
- Role-based route protection.
- Demo account untuk seluruh role.

### Dashboard

Admin, HR, dan Manager:

- Total employee.
- Present, late, dan leave hari ini.
- Pending leave request.
- Total shift.
- Recent attendance dan recent leave.

Employee:

- Attendance hari ini.
- Shift hari ini.
- Leave balance.
- Riwayat attendance dan leave pribadi.

### Employee Management

- CRUD employee untuk Admin dan HR.
- Search dan filter.
- Sinkronisasi user dan employee.
- Soft delete.
- Face enrollment upload.
- Face registration status dan image URL.

### Shift & Schedule

- CRUD shift.
- Regular dan overnight shift.
- Late tolerance.
- Active/inactive status.
- Assignment shift per employee dan tanggal.
- Endpoint bulk schedule, employee schedule, dan date schedule.

### Attendance

- Check-in dan check-out.
- GPS latitude dan longitude.
- Photo evidence wajib untuk attendance utama.
- QR attendance sebagai opsi alternatif.
- Radius validation.
- Status `present` atau `late`.
- Late minute dan overtime minute calculation.
- Monitoring untuk Admin, HR, dan Manager.
- Attendance setting untuk lokasi kantor, radius, dan QR expiry.

### Leave & Approval

- Leave request pribadi.
- Leave balance tahunan.
- Business-day calculation.
- Validasi pengajuan yang bertabrakan.
- Validasi saldo cuti tahunan.
- Cancellation untuk request pending.
- Approval dan rejection.
- Approval history dan rejection reason.

### Reports

- Attendance report.
- Leave report.
- Employee report.
- Filter tanggal, bulan/tahun, department, employee, status, dan keyword.
- Summary per report.
- CSV export.

---

## Role Access

| Module / API Area | Admin | HR | Manager | Employee |
|---|:---:|:---:|:---:|:---:|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Auth Profile | ✅ | ✅ | ✅ | ✅ |
| Employee Management | ✅ | ✅ | ❌ | ❌ |
| Face Enrollment | ✅ | ✅ | ❌ | ❌ |
| Shift Management | ✅ | ✅ | ❌ | ❌ |
| Shift Schedule Management | ✅ | ✅ | ❌ | ❌ |
| Own Attendance | ✅ | ✅ | ✅ | ✅ |
| Check-in / Check-out | ✅ | ✅ | ✅ | ✅ |
| Attendance Monitoring | ✅ | ✅ | ✅ | ❌ |
| Attendance Setting — Read | ✅ | ✅ | ✅ | ❌ |
| Attendance Setting — Write | ✅ | ✅ | ❌ | ❌ |
| QR Generation | ✅ | ✅ | ❌ | ❌ |
| Own Leave Request | ✅ | ✅ | ✅ | ✅ |
| Leave Approval | ✅ | ✅ | ✅ | ❌ |
| Reports | ✅ | ✅ | ✅ | ❌ |

---

## API Endpoint Overview

Local base URL:

```txt
http://localhost:8000/api/v1
```

### Auth

```txt
POST   /auth/login
GET    /auth/me
POST   /auth/logout
POST   /auth/change-password
```

### Dashboard

```txt
GET    /dashboard/summary
```

### Employee

```txt
GET    /employees
POST   /employees
GET    /employees/{employee}
PUT    /employees/{employee}
DELETE /employees/{employee}
GET    /employees/{employee}/profile
POST   /employees/{employee}/face-enrollment
```

### Shift

```txt
GET    /shifts
POST   /shifts
GET    /shifts/{shift}
PUT    /shifts/{shift}
DELETE /shifts/{shift}
```

### Shift Schedule

```txt
GET    /shift-schedules
POST   /shift-schedules
GET    /shift-schedules/{shiftSchedule}
PUT    /shift-schedules/{shiftSchedule}
DELETE /shift-schedules/{shiftSchedule}
GET    /shift-schedules/employee/{employeeId}
GET    /shift-schedules/date/{date}
POST   /shift-schedules/bulk
```

### Attendance

```txt
GET    /attendance/my
GET    /attendance/today
POST   /attendance/check-in
POST   /attendance/check-out
POST   /attendance/check-in/qr
POST   /attendance/check-out/qr

GET    /attendance
GET    /attendance/export
GET    /attendance/employee/{employeeId}
GET    /attendance/{attendance}
```

### Attendance Settings & QR

```txt
GET    /attendance/settings
PUT    /attendance/settings
POST   /attendance/qr/generate
```

### Leave & Approval

```txt
GET    /leaves/my
GET    /leaves/balance
POST   /leaves
GET    /leaves/{leave}
DELETE /leaves/{leave}

GET    /leaves
POST   /leaves/{leave}/approve
POST   /leaves/{leave}/reject
```

### Reports

```txt
GET    /reports/attendance
GET    /reports/leave
GET    /reports/employee
GET    /reports/export?type=attendance|leave|employee&format=csv
```

---

## Attendance Request Examples

### Main Attendance with Photo

Use `multipart/form-data`:

```txt
latitude: -6.200000
longitude: 106.816666
note: Check-in dari kantor
photo: attendance.jpg
```

Supported photo formats:

```txt
jpg, jpeg, png, webp
```

### QR Attendance

```json
{
  "latitude": -6.200000,
  "longitude": 106.816666,
  "qr_code": "<scanned QR value>",
  "note": "Check-in melalui QR"
}
```

---

## Development Roadmap

### Phase 1 — Core Stabilization

- [ ] Organization master data.
- [ ] Employee profile dan emergency contact.
- [ ] Employee document management.
- [ ] Employee self-service.
- [ ] Attendance correction.
- [ ] Generic approval workflow.
- [ ] Audit log.
- [ ] In-app notification.

### Phase 2 — Time, Attendance & Leave Expansion

- [ ] Shift schedule calendar.
- [ ] Bulk dan rotating shift.
- [ ] Day off dan holiday calendar.
- [ ] Overtime request dan approval.
- [ ] Leave type master dan policy.
- [ ] Leave attachment.
- [ ] Half-day leave dan hourly permission.
- [ ] Attendance anomaly detection.

### Phase 3 — Payroll

- [ ] Salary components.
- [ ] Employee salary profile.
- [ ] Payroll periods.
- [ ] Payroll calculation dan approval.
- [ ] Payslip.
- [ ] Payroll report.

### Phase 4 — Portfolio Hardening

- [ ] Backend feature tests.
- [ ] Authorization tests per role.
- [ ] API documentation.
- [ ] Complete demo seeder.
- [ ] Security hardening.
- [ ] Production deployment.
- [ ] Logging, backup, dan monitoring.

Optional future modules:

- Recruitment.
- Onboarding dan offboarding.
- Performance management.
- Reimbursement.
- Asset management.
- Training management.

### Definition of Done

Sebuah modul dianggap selesai apabila:

- Migration dan model tersedia.
- API tervalidasi dan dilindungi role.
- Frontend sudah terintegrasi.
- Loading, empty state, validation error, dan success feedback tersedia.
- Minimal feature test untuk happy path dan authorization tersedia.
- README atau dokumentasi endpoint diperbarui.

---

## Project Structure

```txt
app/
├── Http/
│   ├── Controllers/API/       # API controllers
│   └── Middleware/            # Role middleware
├── Models/                    # Domain models
└── Services/                  # Business logic layer jika dibutuhkan

database/
├── migrations/                # Database schema
└── seeders/                   # Demo and master data

routes/
└── api.php                    # API v1 routes
```

---

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@hris.test` | `password123` |
| HR | `hr@hris.test` | `password123` |
| Manager | `manager@hris.test` | `password123` |
| Employee | `employee@hris.test` | `password123` |

---

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

### Environment Variables

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hris_msr
DB_USERNAME=root
DB_PASSWORD=
```

### Mobile Development

```bash
php artisan optimize:clear
php artisan serve --host=0.0.0.0 --port=8000
ngrok http 8000
```

Set `APP_URL` ke URL backend ngrok, lalu jalankan kembali:

```bash
php artisan optimize:clear
```

Pastikan frontend menggunakan:

```env
VITE_API_BASE_URL=https://your-backend-ngrok-url.ngrok-free.app/api/v1
```

`php artisan storage:link` wajib dijalankan agar foto attendance dan face enrollment dapat diakses.

---

## Pair Repository

Frontend React PWA: [AtsukoAditia/hris-fe-msr](https://github.com/AtsukoAditia/hris-fe-msr)

---

## Author

**Aditia Nugraha** — [@AtsukoAditia](https://github.com/AtsukoAditia)
