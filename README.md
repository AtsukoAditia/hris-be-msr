# HRIS Backend — hris-be-msr

> **Smart Attendance HRIS** — Laravel API  
> Bagian backend dari sistem HRIS yang menyediakan REST API untuk absensi, approval realtime, shift mapping, payroll, dan laporan kehadiran.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 |
| Authentication | Laravel Sanctum (API Token) |
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
- [ ] Auth API (Login, Logout, Refresh Token - Sanctum)
- [ ] Management Pegawai (CRUD)
- [ ] Management Role & Permission
- [ ] Shift Mapping per tanggal
- [ ] Absensi (Selfie + Foto + Geolocation)
- [ ] Absensi QR Code
- [ ] Pengajuan Cuti & Izin
- [ ] Approval System + Broadcasting Realtime
- [ ] Laporan Absensi (filter user & range tanggal)
- [ ] Rekap Data Kehadiran
- [ ] Notifikasi WhatsApp

---

## Struktur Folder

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/           # AuthController
│   │   ├── Attendance/     # AttendanceController
│   │   ├── Employee/       # EmployeeController
│   │   ├── Shift/          # ShiftController, ShiftMappingController
│   │   ├── Leave/          # LeaveController
│   │   ├── Approval/       # ApprovalController
│   │   ├── Report/         # ReportController
│   │   └── Dashboard/      # DashboardController
│   ├── Middleware/         # Auth, Role middleware
│   └── Requests/           # Form Request Validation per modul
├── Models/
│   ├── User.php
│   ├── Employee.php
│   ├── Attendance.php
│   ├── Shift.php
│   ├── ShiftMapping.php
│   ├── Leave.php
│   ├── LeaveType.php
│   └── Approval.php
├── Events/                 # Broadcasting events (ApprovalUpdated, dll)
├── Notifications/          # WhatsApp & system notifications
├── Services/               # Business logic layer
└── Jobs/                   # Queue jobs (notifikasi, rekap)

database/
├── migrations/             # Semua migration per modul
└── seeders/                # Seeder untuk data dummy & roles

routes/
├── api.php                 # Semua API routes
└── channels.php            # Broadcasting channels
```

---

## API Endpoint (Overview)

```
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/employees
POST   /api/employees
GET    /api/attendance
POST   /api/attendance/checkin
POST   /api/attendance/checkout
GET    /api/shifts
POST   /api/shift-mapping
GET    /api/leaves
POST   /api/leaves
PATCH  /api/approvals/{id}
GET    /api/reports/attendance
GET    /api/reports/recap
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
php artisan migrate --seed

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
