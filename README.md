# HRIS Backend — hris-be-msr

> **Smart Attendance HRIS** — Laravel REST API  
> Backend untuk sistem HRIS berbasis role yang menangani autentikasi, dashboard, employee management, shift management, attendance dengan geolocation + photo evidence, leave, approval, dan report.

---

## Status Project

Project ini sedang dibangun bertahap sebagai portfolio / TA-style HRIS web app dengan fokus backend API yang stabil dan mudah diintegrasikan dengan frontend PWA.

| Modul | Status | Keterangan |
|---|---:|---|
| Modul 1 — Foundation / API Sync | ✅ Done | Struktur API v1, route alignment, response dasar |
| Modul 2 — Auth & Role Access | ✅ Done | Login, logout, `/auth/me`, Sanctum token, role middleware |
| Modul 3 — Dashboard Summary | ✅ Done | Ringkasan dashboard berdasarkan role |
| Modul 4 — Employee Management | ✅ Done | CRUD employee, user mapping, face enrollment image |
| Modul 5 — Shift Management | ✅ Done | CRUD shift, active/inactive, overnight shift, late tolerance |
| Modul 6 — Attendance | ✅ Done | Check-in/out, GPS, mobile camera photo evidence, late/overtime calculation |
| Modul 7 — Leave Request + Approval | ⏳ Next | Pengajuan cuti/izin dan approval flow |
| Modul 8 — Attendance Report + Export | ⏳ Planned | Filter report dan export CSV/Excel |
| Modul 9 — Radius + QR | ⏳ Planned | Validasi radius lokasi dan QR attendance |
| Modul 10 — Docs / Deploy | ⏳ Planned | Dokumentasi final dan deployment |

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
| Storage | Laravel Storage public disk |
| File Upload | Image upload for face enrollment and attendance evidence |
| API Format | REST JSON API |

---

## Completed Features

### Auth & Role Access

- Login with Sanctum token.
- Logout.
- Authenticated user profile via `/auth/me`.
- Role-based API route protection.
- Standardized auth payload with nested employee data.
- Demo users for Admin, HR, Manager, and Employee.

### Dashboard Summary

- Role-aware dashboard summary.
- Admin/HR/Manager summary includes employee count, attendance status, pending leave, shift count, recent attendance, and recent leave.
- Employee dashboard includes personal attendance today, leave balance, today shift, and personal recent records.

### Employee Management

- Employee CRUD for Admin/HR.
- Search and filters by department, status, keyword.
- User + employee data synchronization.
- Employee soft delete.
- Face enrollment upload.
- Face image URL and registration status in API response.

### Shift Management

- Shift CRUD for Admin/HR.
- Search by name, code, and description.
- Filter active/inactive shifts.
- Support regular and overnight shift.
- Late tolerance in minutes.
- Auto uppercase shift code.
- Used shift is deactivated instead of hard-deleted.

### Attendance

- Employee check-in.
- Employee check-out.
- Check-in/check-out with GPS latitude and longitude.
- Optional photo evidence for check-in and check-out.
- Mobile camera upload support.
- Attendance photo URL response.
- Auto status `present` or `late` based on shift start time + late tolerance.
- Late minute calculation.
- Overtime minute calculation.
- Admin/HR/Manager attendance monitoring.

---

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@hris.test` | `password123` |
| HR | `hr@hris.test` | `password123` |
| Manager | `manager@hris.test` | `password123` |
| Employee | `employee@hris.test` | `password123` |

---

## Role Access

| Module / API Area | Admin | HR | Manager | Employee |
|---|---:|---:|---:|---:|
| Dashboard Summary | ✅ | ✅ | ✅ | ✅ |
| Auth Profile | ✅ | ✅ | ✅ | ✅ |
| Employee Management | ✅ | ✅ | ❌ | ❌ |
| Face Enrollment | ✅ | ✅ | ❌ | ❌ |
| Shift Management | ✅ | ✅ | ❌ | ❌ |
| Shift Schedule Management | ✅ | ✅ | ❌ | ❌ |
| Own Attendance | ✅ | ✅ | ✅ | ✅ |
| Check-in / Check-out | ✅ | ✅ | ✅ | ✅ |
| Attendance Monitoring | ✅ | ✅ | ✅ | ❌ |
| Own Leave Request | ✅ | ✅ | ✅ | ✅ |
| Leave Approval | ✅ | ✅ | ✅ | ❌ |
| Reports | ✅ | ✅ | ✅ | ❌ |

---

## API Endpoint Overview

Base URL local:

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
GET    /employees/profile
GET    /employees/{employee}
PUT    /employees/{employee}
DELETE /employees/{employee}
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
GET    /shift-schedules/my
DELETE /shift-schedules/{shiftSchedule}
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
GET    /attendance/{attendance}
GET    /attendance/employee/{employeeId}
GET    /attendance/export
```

### Leave & Approval

```txt
GET    /leaves/my
GET    /leaves/balance
POST   /leaves
GET    /leaves
GET    /leaves/{leave}
PUT    /leaves/{leave}
DELETE /leaves/{leave}
POST   /leaves/{leave}/approve
POST   /leaves/{leave}/reject
```

### Reports

```txt
GET    /reports/attendance
GET    /reports/leave
GET    /reports/employee
```

---

## Attendance Request Example

### Check-in JSON

```http
POST /api/v1/attendance/check-in
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json

{
  "latitude": -6.200000,
  "longitude": 106.816666,
  "note": "Check-in dari kantor"
}
```

### Check-in with Photo Evidence

Use `multipart/form-data`:

```txt
latitude: -6.200000
longitude: 106.816666
note: Check-in dari kantor
photo: attendance.jpg
```

Photo validation:

```txt
jpg, jpeg, png, webp
max 8192 KB
```

---

## Struktur Folder

```txt
app/
├── Http/
│   ├── Controllers/API/       # Auth, Dashboard, Employee, Shift, Attendance, Leave, Report
│   └── Middleware/            # Role middleware
├── Models/                    # User, Employee, Shift, ShiftSchedule, Attendance, Leave
└── Services/                  # Business logic layer, jika dibutuhkan

database/
├── migrations/                # Table schema
└── seeders/                   # Demo data and user seeder

routes/
└── api.php                    # API v1 routes
```

---

## Cara Menjalankan Lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

Untuk development dengan mobile/ngrok:

```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan serve --host=0.0.0.0 --port=8000
```

---

## Environment Variables

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hris_msr
DB_USERNAME=root
DB_PASSWORD=
```

Jika menggunakan ngrok untuk test HP:

```env
APP_URL=https://your-backend-ngrok-url.ngrok-free.app
```

Lalu jalankan:

```bash
php artisan optimize:clear
```

---

## Mobile Testing Notes

Untuk test fitur kamera dan GPS di HP:

1. Jalankan backend Laravel dengan `--host=0.0.0.0`.
2. Expose backend menggunakan ngrok.
3. Pastikan frontend `.env` mengarah ke backend ngrok:

```env
VITE_API_BASE_URL=https://your-backend-ngrok-url.ngrok-free.app/api/v1
```

4. Jalankan `php artisan storage:link` agar evidence photo bisa dibuka.

---

## Pair Repository

Frontend PWA: [hris-fe-msr](https://github.com/AtsukoAditia/hris-fe-msr)

---

## Author

**Aditia Nugraha** — [@AtsukoAditia](https://github.com/AtsukoAditia)
