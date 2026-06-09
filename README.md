# HRIS Backend — hris-be-msr

> **Smart Attendance HRIS** — Laravel API  
> Bagian backend dari sistem HRIS yang menyediakan REST API untuk absensi, approval realtime, shift mapping, payroll, dan laporan kehadiran.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Authentication | Laravel Sanctum (API Token) |
| Authorization | Role Middleware |
| Realtime | Laravel Broadcasting + Pusher |
| Database | MySQL 8 |
| Cache & Queue | Redis / Database Queue |
| Notification | WhatsApp API (via Fonnte/Wablas) |
| Storage | Laravel Storage (local/S3) |
| Scheduler | Laravel Task Scheduling |
| API Docs | Postman Collection |

---

## Fitur MVP

- [x] Struktur project Laravel
- [x] Auth API (Login, Logout, Change Password - Sanctum)
- [x] Role-Based API Access
- [x] Demo User Seeder
- [ ] Management Pegawai (CRUD)
- [ ] Shift Mapping per tanggal
- [ ] Absensi (Selfie + Foto + Geolocation)
- [ ] Absensi QR Code
- [ ] Pengajuan Cuti & Izin
- [ ] Approval System + Broadcasting Realtime
- [ ] Laporan Absensi (filter user & range tanggal)
- [ ] Rekap Data Kehadiran
- [ ] Notifikasi WhatsApp

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

| Module | Admin | HR | Manager | Employee |
|---|---:|---:|---:|---:|
| Dashboard Summary | ✅ | ✅ | ✅ | ✅ |
| Own Attendance | ✅ | ✅ | ✅ | ✅ |
| Check-in / Check-out | ✅ | ✅ | ✅ | ✅ |
| Attendance List & Detail | ✅ | ✅ | ✅ | ❌ |
| Employee Management | ✅ | ✅ | ❌ | ❌ |
| Shift Management | ✅ | ✅ | ❌ | ❌ |
| Shift Schedule Management | ✅ | ✅ | ❌ | ❌ |
| Own Leave Request | ✅ | ✅ | ✅ | ✅ |
| Leave Approval | ✅ | ✅ | ✅ | ❌ |
| Reports | ✅ | ✅ | ✅ | ❌ |

---

## Struktur Folder

```txt
app/
├── Http/
│   ├── Controllers/
│   │   └── API/              # Auth, Attendance, Employee, Shift, Leave, Report
│   ├── Middleware/           # Auth, Role middleware
│   └── Requests/             # Form Request Validation per modul
├── Models/
│   ├── User.php
│   ├── Employee.php
│   ├── Attendance.php
│   ├── Shift.php
│   ├── ShiftSchedule.php
│   └── Leave.php
├── Events/                   # Broadcasting events
├── Notifications/            # WhatsApp & system notifications
├── Services/                 # Business logic layer
└── Jobs/                     # Queue jobs

database/
├── migrations/               # Semua migration per modul
└── seeders/                  # Seeder untuk data dummy & roles

routes/
├── api.php                   # Semua API routes
└── channels.php              # Broadcasting channels
```

---

## API Endpoint (Overview)

Base URL local:

```txt
http://localhost:8000/api/v1
```

```txt
POST   /auth/login
GET    /auth/me
POST   /auth/logout
POST   /auth/change-password

GET    /dashboard/summary

GET    /employees                 # admin, hr
POST   /employees                 # admin, hr
GET    /employees/{employee}      # admin, hr
PUT    /employees/{employee}      # admin, hr
DELETE /employees/{employee}      # admin, hr

GET    /attendance/my
GET    /attendance/today
POST   /attendance/check-in
POST   /attendance/check-out
POST   /attendance/check-in/qr
POST   /attendance/check-out/qr
GET    /attendance                # admin, hr, manager
GET    /attendance/{attendance}   # admin, hr, manager

GET    /shifts                    # admin, hr
POST   /shifts                    # admin, hr
GET    /shift-schedules           # admin, hr
POST   /shift-schedules           # admin, hr
POST   /shift-schedules/bulk      # admin, hr

GET    /leaves/my
POST   /leaves
GET    /leaves                    # admin, hr, manager
POST   /leaves/{leave}/approve    # admin, hr, manager
POST   /leaves/{leave}/reject     # admin, hr, manager

GET    /reports/attendance        # admin, hr, manager
GET    /reports/leave             # admin, hr, manager
GET    /reports/employee          # admin, hr, manager
```

---

## Cara Menjalankan

```bash
# Clone & install
composer install

# Copy env
cp .env.example .env
php artisan key:generate

# Migrate & seed
php artisan migrate:fresh --seed

# Jalankan server
php artisan serve

# Jalankan queue worker
php artisan queue:work
```

---

## Environment Variables

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hris_msr
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=ap1

WHATSAPP_API_URL=https://api.fonnte.com/send
WHATSAPP_API_TOKEN=your_token
```

---

## Pair Repository

Frontend PWA: [hris-fe-msr](https://github.com/AtsukoAditia/hris-fe-msr)

---

## Author

**Aditia Nugraha** — [@AtsukoAditia](https://github.com/AtsukoAditia)
