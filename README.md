# HRIS Backend — `hris-be-msr`

Laravel REST API untuk **Smart Attendance HRIS**, terhubung dengan frontend React PWA [`hris-fe-msr`](https://github.com/AtsukoAditia/hris-fe-msr).

> **Status terakhir diverifikasi:** 20 Juni 2026  
> Branch utama: `main`

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3+ |
| Database | MySQL 8 |
| Authentication | Laravel Sanctum |
| Authorization | Role middleware, policy, ownership, dan manager scope |
| ORM | Eloquent |
| File Storage | Laravel Filesystem private disk |
| Testing | PHPUnit / Laravel Feature Tests |
| Code Style | Laravel Pint |
| CI | GitHub Actions |

## API Base URL

```text
http://localhost:8000/api/v1
```

## Current Project Status

| Module | Backend | Frontend | Status |
|---|:---:|:---:|---|
| Foundation, API v1, Authentication & RBAC | ✅ | ✅ | Synced |
| Role-based Dashboard | ✅ | ✅ | Synced |
| Organization Master: Department, Position, Branch | ✅ | ✅ | Completed |
| Employee Management & Direct Manager Relation | ✅ | ✅ | Completed |
| Employee Profile, Emergency Contact & Documents | ✅ | ✅ | Completed |
| Employee Self-Service & Profile Change Approval | ✅ | ✅ | Completed |
| Shift & Basic Shift Schedule | ✅ | ✅ | Completed |
| Attendance: GPS, Photo, Radius & QR | ✅ | ✅ | Completed |
| Attendance Correction | ✅ | ✅ | Completed |
| Activity Log Viewer | ✅ | ✅ | Completed |
| Leave Request, Approval, Balance & History | ✅ | ✅ | Completed |
| Leave Type, Policy, Holiday & Balance Administration | ✅ | ✅ | Completed |
| Attendance, Leave & Employee Reports + CSV | ✅ | ✅ | Completed |
| **Overtime Policy & Request Workflow** | ✅ | ✅ | **Completed** |
| Payroll Foundation | ⬜ | ⬜ | Planned |

## Current Backend Milestone — Overtime Management

Overtime sudah tersedia end-to-end dan disinkronkan dengan frontend:

- Overtime policy CRUD untuk Admin dan HR.
- Active overtime policy options untuk seluruh user terautentikasi.
- Pengajuan overtime oleh authenticated employee.
- Daftar dan detail overtime berdasarkan scope role.
- Endpoint `my` selalu dibatasi ke employee milik actor, termasuk untuk Admin, HR, dan Manager.
- Pembatalan request selama masih `pending`.
- Approval dan rejection oleh Admin, HR, atau direct manager yang berwenang.
- Pencatatan actual overtime minutes oleh Admin dan HR.
- Validasi batas maksimum overtime berdasarkan policy aktif.
- Penyimpanan rate multiplier dari policy.
- Transaction dan row locking pada proses status kritis.
- Frontend responsive untuk request, review, actual minutes, dan policy administration.
- Backend tests, frontend component tests, production build, dan mobile acceptance melalui CI.

### Overtime Endpoints

```http
GET    /api/v1/overtime-policies

GET    /api/v1/overtime-requests/my
POST   /api/v1/overtime-requests
GET    /api/v1/overtime-requests/{overtimeRequest}
POST   /api/v1/overtime-requests/{overtimeRequest}/cancel

GET    /api/v1/overtime-requests
POST   /api/v1/overtime-requests/{overtimeRequest}/approve
POST   /api/v1/overtime-requests/{overtimeRequest}/reject
POST   /api/v1/overtime-requests/{overtimeRequest}/record-actual
```

Overtime policy administration:

```http
GET    /api/v1/admin/overtime-policies
POST   /api/v1/admin/overtime-policies
GET    /api/v1/admin/overtime-policies/{overtimePolicy}
PUT    /api/v1/admin/overtime-policies/{overtimePolicy}
PATCH  /api/v1/admin/overtime-policies/{overtimePolicy}
DELETE /api/v1/admin/overtime-policies/{overtimePolicy}
```

## Attendance Correction

Attendance Correction sudah tersedia end-to-end untuk employee dan reviewer.

Employee dapat:

- Melihat request miliknya.
- Mengajukan koreksi check-in, check-out, atau keduanya.
- Melampirkan bukti.
- Melihat detail dan status.
- Membatalkan request `pending`.

Admin, HR, dan Manager sesuai scope dapat:

- Melihat dan memfilter request.
- Membandingkan attendance asli dengan nilai yang diminta.
- Approve atau reject.
- Mengunduh attachment secara terautentikasi.
- Melakukan manual correction untuk role yang diizinkan.

## Leave Management

Leave module mencakup:

- Employee leave request, history, detail, dan cancellation.
- Leave balance per type dan tahun.
- Admin/HR/Manager approval dan rejection sesuai authorization.
- Leave type master.
- Leave policy configuration.
- Holiday master.
- Leave balance administration dan adjustment.
- Working-day calculation dan leave balance transaction handling.

## Employee Self-Service

Employee Self-Service memisahkan perubahan data menjadi:

1. **Direct update** untuk data kontak dan domisili.
2. **Profile change request** untuk data legal, identitas, dan benefit yang memerlukan review Admin/HR.

Endpoint utama:

```http
GET   /api/v1/profile/me
PATCH /api/v1/profile/me

GET    /api/v1/profile/change-requests
POST   /api/v1/profile/change-requests
GET    /api/v1/profile/change-requests/{profileChangeRequest}
DELETE /api/v1/profile/change-requests/{profileChangeRequest}
```

Dokumen employee disimpan pada private disk dan hanya dapat diunduh melalui authenticated endpoint dengan role atau ownership check.

## Activity Log

Activity log menyimpan informasi seperti:

- Actor.
- Module dan action.
- Endpoint dan HTTP method.
- Response status.
- Request dan response preview.
- IP address dan waktu aktivitas.

Password, token, dan data sensitif difilter. Binary upload tidak disimpan ke audit payload.

Admin dan HR dapat mengakses viewer melalui:

```http
GET /api/v1/activity-logs
GET /api/v1/activity-logs/{activityLog}
```

## Security and Business Rules

- Semua endpoint bisnis dilindungi Sanctum.
- Role dari frontend tidak dipercaya sebagai sumber authorization.
- Employee dibatasi pada resource miliknya.
- Manager dibatasi pada direct subordinate sesuai relasi organisasi.
- Operasi approval menggunakan transaction; operasi kritis menggunakan row locking saat diperlukan.
- File sensitif disimpan pada private disk.
- List besar menggunakan pagination.
- Form Request digunakan untuk validasi payload.
- Soft delete digunakan pada data yang membutuhkan lifecycle historis.

## Testing and CI

Jalankan secara lokal:

```bash
composer test
vendor/bin/pint --test
```

Backend CI menjalankan:

1. Composer validation.
2. Dependency installation.
3. MySQL service dan database migration.
4. Laravel Pint pada file PHP yang berubah.
5. Full backend test suite.
6. Upload test log sebagai diagnostic artifact.

Status CI pada milestone terbaru: **passing**.

## Local Setup

```bash
git clone https://github.com/AtsukoAditia/hris-be-msr.git
cd hris-be-msr
composer install
cp .env.example .env
php artisan key:generate
```

Atur koneksi MySQL pada `.env`, lalu jalankan:

```bash
php artisan migrate --seed
php artisan serve
```

API tersedia secara default di:

```text
http://localhost:8000/api/v1
```

`php artisan storage:link` hanya diperlukan untuk asset public. Employee documents dan attachment sensitif tetap berada pada private disk.

## Project Documentation

```text
docs/PROJECT_STATUS.md
docs/ROADMAP.md
docs/API_MATRIX.md
docs/employee-self-service.md
docs/employee-document-management.md
docs/employee-profile-emergency-contact.md
```

## Definition of Done

Modul dinyatakan selesai setelah database, model, service, API, authorization, validation, transaction safety, tests, CI, frontend integration, mobile acceptance, dan dokumentasi telah sinkron.

## Next Focus

**Basic Payroll Foundation**, menggunakan approved overtime dan actual minutes sebagai input payroll-ready.
