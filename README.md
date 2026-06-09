# HRIS Frontend — hris-fe-msr

> **Smart Attendance HRIS** — React + Vite + PWA  
> Frontend untuk sistem HRIS berbasis role yang menangani dashboard, employee management, shift management, attendance dengan GPS + mobile camera photo evidence, leave, approval, dan report.

---

## Status Project

Project ini dibangun bertahap sebagai HRIS web app yang dapat dijalankan sebagai PWA dan diuji melalui browser desktop maupun mobile Android.

| Modul | Status | Keterangan |
|---|---:|---|
| Modul 1 — Foundation / API Sync | ✅ Done | API base URL, route sync, protected layout |
| Modul 2 — Auth & Role Access | ✅ Done | Login, logout, auth hydration, role-based sidebar/routes |
| Modul 3 — Dashboard Summary | ✅ Done | Dashboard role-aware dari API `/dashboard/summary` |
| Modul 4 — Employee Management | ✅ Done | CRUD employee, detail modal, face enrollment upload |
| Modul 5 — Shift Management | ✅ Done | CRUD shift, search/filter, overnight, late tolerance |
| Modul 6 — Attendance | ✅ Done | Check-in/out, GPS, mobile camera photo evidence |
| Modul 7 — Leave Request + Approval | ⏳ Next | Form cuti/izin dan approval flow |
| Modul 8 — Attendance Report + Export | ⏳ Planned | Report filter dan export |
| Modul 9 — Radius + QR | ⏳ Planned | QR attendance dan radius validation |
| Modul 10 — Docs / Deploy | ⏳ Planned | Dokumentasi final dan deployment |

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | React 18 + Vite |
| Styling | Tailwind CSS |
| Routing | React Router v6 |
| State Management | Zustand |
| HTTP Client | Axios |
| Icons | Lucide React |
| PWA | vite-plugin-pwa + Workbox |
| Camera Upload | Browser file input with `capture` |
| Geolocation | Browser Geolocation API |
| Mobile Testing | ngrok tunnel |

---

## Completed Features

### Auth & Role Access

- Login with API token.
- Logout.
- Auth state persistence with Zustand.
- Sync user data via `/auth/me`.
- Protected route hydration.
- Role-based sidebar menu.
- Role-based page access.
- ngrok API request support with `ngrok-skip-browser-warning` header.

### Dashboard Summary

- Dashboard data from backend API.
- Role-aware dashboard cards.
- Employee personal summary.
- Admin/HR/Manager operational summary.

### Employee Management

- Employee list with search/filter.
- Add employee.
- Edit employee.
- Delete employee.
- Detail employee modal.
- Face enrollment upload.
- Face registration status and photo preview.

### Shift Management

- Shift list.
- Add shift.
- Edit shift.
- Delete/nonaktif shift.
- Search by name/code/description.
- Filter active/inactive.
- Summary cards: total, active, overnight.
- Support regular and overnight shift.
- Late tolerance field.

### Attendance

- Employee check-in.
- Employee check-out.
- Check-in with GPS.
- Check-out with GPS.
- Check-in + photo evidence.
- Check-out + photo evidence.
- Android mobile camera support.
- Camera photo normalization:
  - convert to JPEG
  - resize max 1280px
  - compress before upload
- Evidence links for check-in/check-out photo.
- Admin/HR/Manager attendance monitoring.
- Attendance status badge.
- Late minutes display.

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

| Menu / Page | Admin | HR | Manager | Employee |
|---|---:|---:|---:|---:|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Attendance | ✅ | ✅ | ✅ | ✅ |
| Leave | ✅ | ✅ | ✅ | ✅ |
| Approval | ✅ | ✅ | ✅ | ❌ |
| Report | ✅ | ✅ | ✅ | ❌ |
| Employee | ✅ | ✅ | ❌ | ❌ |
| Shift | ✅ | ✅ | ❌ | ❌ |
| Shift Schedule | ✅ | ✅ | ❌ | ❌ |

---

## Struktur Folder

```txt
src/
├── assets/                    # Static assets
├── components/
│   ├── layout/                # Sidebar, layout shell
│   ├── shift/                 # Shift list and form modal
│   └── ui/                    # Reusable UI components
├── hooks/                     # Custom hooks
├── lib/
│   ├── axios.js               # Axios instance + interceptor
│   └── utils.js               # Utility functions
├── pages/
│   ├── auth/                  # Login page
│   ├── dashboard/             # Dashboard summary
│   ├── employee/              # Employee management
│   ├── shift/                 # Shift management
│   ├── attendance/            # Check-in/out, GPS, photo evidence
│   ├── leave/                 # Leave request
│   ├── approval/              # Approval page
│   └── report/                # Reports
├── routes/                    # React Router config + ProtectedRoute
├── services/                  # API services per module
└── store/                     # Zustand stores
```

---

## Environment Variables

Buat file `.env` di root project:

```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

Untuk test HP via ngrok:

```env
VITE_API_BASE_URL=https://your-backend-ngrok-url.ngrok-free.app/api/v1
```

Setelah mengubah `.env`, restart Vite.

---

## Cara Menjalankan Lokal

```bash
npm install
npm run dev
```

Jika ingin diakses dari HP dalam jaringan lokal atau ngrok:

```bash
npm run dev -- --host 0.0.0.0
```

Default dev server:

```txt
http://localhost:3000
```

---

## Mobile Testing with ngrok

### 1. Jalankan backend Laravel

```bash
cd hris-be-msr
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Jalankan frontend Vite

```bash
cd hris-fe-msr
npm run dev -- --host 0.0.0.0
```

### 3. Expose backend dan frontend dengan ngrok

```bash
ngrok http 8000
ngrok http 3000
```

### 4. Update `.env` frontend

```env
VITE_API_BASE_URL=https://your-backend-ngrok-url.ngrok-free.app/api/v1
```

### 5. Restart Vite

```bash
npm run dev -- --host 0.0.0.0
```

### 6. Buka frontend ngrok di HP

```txt
https://your-frontend-ngrok-url.ngrok-free.app
```

---

## PWA / Cache Troubleshooting

Karena project menggunakan PWA + Workbox, browser bisa menyimpan service worker lama saat development.

Jika perubahan frontend tidak muncul, jalankan di browser console:

```js
navigator.serviceWorker.getRegistrations().then((regs) => {
  regs.forEach((reg) => reg.unregister())
})

caches.keys().then((keys) => {
  keys.forEach((key) => caches.delete(key))
})

localStorage.removeItem('hris-auth-storage')
location.reload()
```

---

## ngrok Notes

Vite sudah dikonfigurasi untuk mengizinkan host ngrok:

```js
server: {
  host: true,
  allowedHosts: [
    '.ngrok-free.app',
    '.ngrok.app',
    '.ngrok.io',
  ],
}
```

Axios juga mengirim header ini untuk melewati browser warning ngrok:

```txt
ngrok-skip-browser-warning: true
```

---

## Attendance Photo Notes

Untuk mendukung kamera Android, file foto dari input kamera akan diproses di frontend sebelum upload:

```txt
1. Validasi file kamera tidak kosong
2. Load image dari object URL
3. Resize max 1280px
4. Convert ke JPEG
5. Compress quality 0.82
6. Upload sebagai multipart/form-data
```

Ini membuat upload camera dari HP lebih stabil dibanding mengirim file kamera mentah.

---

## Pair Repository

Backend API: [hris-be-msr](https://github.com/AtsukoAditia/hris-be-msr)

---

## Author

**Aditia Nugraha** — [@AtsukoAditia](https://github.com/AtsukoAditia)
