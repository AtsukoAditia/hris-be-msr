# HRIS-MSR — Panduan Pengguna

## 1. Login & Akun Default

### Credential
| Role | Email | Password |
|---|---|---|
| Admin | `admin@hris.test` | `password123` |
| HR | `hr@hris.test` | `password123` |
| Manager | `manager@hris.test` | `password123` |
| Employee | `employee@hris.test` | `password123` |

### Reset Password
Gunakan fitur **"Lupa Password"** di halaman login, atau hubungi admin untuk reset manual via database.

---

## 2. Matrix Hak Akses per Role

| Modul | Admin | HR | Manager | Employee |
|---|---|---|---|---|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Absensi | ✅ | ✅ | ✅ | ✅ |
| Koreksi Absensi | ✅ | ✅ | ✅ | ✅ |
| Cuti | ✅ | ✅ | ✅ | ✅ |
| Lembur | ✅ | ✅ | ✅ | ✅ |
| Jadwal Saya | — | — | ✅ | ✅ |
| Slip Gaji | — | — | — | ✅ |
| Persetujuan | ✅ | ✅ | ✅ | — |
| Review Profil | ✅ | ✅ | — | — |
| Laporan | ✅ | ✅ | ✅ | — |
| Departemen | ✅ | ✅ | ✅ | — |
| Karyawan | ✅ | ✅ | — | — |
| Shift | ✅ | ✅ | — | — |
| Jadwal Shift | ✅ | ✅ | ✅ | — |
| Master Cuti | ✅ | ✅ | — | — |
| Payroll | ✅ | ✅ | — | — |
| Audit Log | ✅ | ✅ | — | — |

> **Sub-menu "Profil Saya"** (Data Profil, Perubahan Profil, Keamanan Akun, Dokumen Saya) visible untuk **semua role**.

---

## 3. Panduan per Role

### 3.1 Admin

**Login:** `admin@hris.test` / `password123`

Admin punya akses penuh ke seluruh modul.

#### A. Dashboard
Buka `/dashboard`. Lihat ringkasan: total karyawan, absensi hari ini, cuti pending, payroll period aktif.

#### B. Manajemen Karyawan (`/employees`)
1. Klik **"+ Tambah Karyawan"**
2. Isi: Nama, Email, NIK, Departemen, Posisi, Tanggal Masuk, Role
3. Sistem auto-generate password default (`password123`) — karyawan wajib ganti di pertama login
4. Edit / Nonaktifkan karyawan dari menu titik tiga

#### C. Master Data — Departemen (`/master-data`)
1. Lihat daftar departemen & cabang
2. Tambah departemen baru
3. Assign manager ke departemen

#### D. Shift (`/shift`)
1. Buat shift baru: Nama (misal "Pagi"), Jam Masuk, Jam Pulang, Durasi
2. Shift otomatis tersedia di Jadwal Shift

#### E. Jadwal Shift (`/shift-schedule`)
1. Pilih bulan & departemen
2. Klik cell tanggal → assign shift ke karyawan
3. **Bulk Assign**: pilih range tanggal → assign shift sekaligus ke banyak karyawan
4. **Copy Week**: salin jadwal dari minggu lalu
5. **Rotating Shift**: generate jadwal putar otomatis (Pagi→Siang→Malam)
6. Klik **"Publish"** agar jadwal visible ke manager & employee
7. **Swap Request**: handle penukaran jadwal antar karyawan

#### F. Master Cuti (`/leave/master`)
1. Buat jenis cuti baru: Nama (Tahunan/Sakit/Dll), Jumlah Hari default
2. Set annual reset: apakah hari cuti di-reset tiap tahun

#### G. Payroll (`/payroll`)
1. Buka period payroll → pilih bulan
2. **Adjustment**: tambah/potong (bonus, tunjangan, denda, dll)
3. **Simulasi**: lihat total sebelum approve
4. **Approval Workflow**: Approve per tahap (HR → Manager → Finance → Paid)
5. **Lock Period**: kunci periode payroll agar tidak bisa diedit lagi
6. Generate slip gaji → otomatis tersedia di menu Slip Gaji employee

#### H. Review Profil (`/profile-change-reviews`)
Lihat permintaan perubahan data karyawan. Approve/Reject perubahan data pribadi employee.

#### I. Audit Log (`/audit-log`)
Catat semua perubahan sistem: siapa mengubah apa, kapan.

#### J. Laporan (`/report`)
- **Kehadiran**: export Excel absensi per periode
- **Cuti**: rekap cuti per karyawan
- **Karyawan**: daftar karyawan per departemen/cabang

---

### 3.2 HR

**Login:** `hr@hris.test` / `password123`

HR fokus ke operasi harian: absensi, cuti, karyawan, dan payroll.

#### A. Dashboard
RingkasanHR: karyawan aktif, cuti hari ini, absensi terkini.

#### B. Manajemen Karyawan (`/employees`)
Sama seperti admin — bisa tambah, edit, nonaktifkan karyawan.

#### C. Absensi (`/attendance`)
1. Filter: Tanggal, Karyawan, Departemen, Status
2. View detail per karyawan
3. **Export**: download laporan absensi

#### D. Koreksi Absensi (`/correction`)
1. Lihat daftar permintaan koreksi dari karyawan
2. **Approve**: absensi dikoreksi sesuai request
3. **Reject**: request ditolak
4. Request manual koreksi替员工申请

#### E. Cuti (`/leave`)
1. Lihat semua pengajuan cuti
2. **Approve / Reject** pengajuan
3. Lihat sisa cuti tiap karyawan

#### F. Jadwal Shift (`/shift-schedule`)
Sama flow dengan admin: assign shift, bulk assign, copy week, rotating, publish.

#### G. Master Cuti (`/leave/master`)
Sama dengan admin: manajemen jenis cuti & kebijakan.

#### H. Payroll (`/payroll`)
Sama dengan admin: adjustment, approval workflow, lock period.

#### I. Review Profil (`/profile-change-reviews`)
Sama dengan admin: approve/reject perubahan data karyawan.

#### J. Audit Log (`/audit-log`)
Lihat log perubahan sistem.

#### K. Laporan (`/report`)
Export laporan kehadiran, cuti, employee.

---

### 3.3 Manager

**Login:** `manager@hris.test` / `password123`

Manager fokus ke tim dan approval.

#### A. Dashboard
Ringkasan tim: siapa yang absent, cuti pending, jadwal shift tim.

#### B. Jadwal Shift (`/shift-schedule`)
1. Lihat jadwal shift seluruh tim
2. **Team Schedule**: filter per departemen
3. Handle **Swap Request**: approve/reject penukaran jadwal antar employee
4. Publish jadwal untuk tim

#### C. Persetujuan (`/approval`)
1. **Cuti**: approve/reject pengajuan cuti dari tim
2. **Koreksi Absensi**: approve/reject koreksi absensi tim
3. **Lembur**: approve/reject request lembur

#### D. Laporan (`/report`)
Export laporan kehadiran & cuti untuk tim saja.

#### E. Data Profil Sendiri
- **Profil Saya** (`/profile`): edit data pribadi
- **Perubahan Profil** (`/profile/change-requests`): ajukan perubahan data
- **Keamanan Akun** (`/security`): ganti password
- **Dokumen Saya** (`/documents`): upload/download dokumen pribadi
- **Jadwal Saya** (`/my-schedule`): lihat jadwal shift pribadi
- **Absensi** (`/attendance`): check-in/check-out harian
- **Koreksi Absensi** (`/correction`): ajukan koreksi jika ada kesalahan
- **Cuti** (`/leave`): ajukan cuti, lihat status & saldo
- **Lembur** (`/overtime`): ajukan lembur

---

### 3.4 Employee

**Login:** `employee@hris.test` / `password123`

Employee punya akses paling terbatas — fokus ke self-service.

#### A. Dashboard
Profil singkat: nama, departemen, jam shift hari ini, saldo cuti.

#### B. Absensi (`/attendance`)
1. **Check In** — tombol utama di halaman
2. **Check Out** — saat pulang
3. **QR Code** — absensi via QR (jika kantor pakai)
4. Riwayat absensi harian

#### C. Jadwal Saya (`/my-schedule`)
Lihat jadwal shift pribadi per minggu/bulan. Published schedule only.

#### D. Slip Gaji (`/payslips`)
Download slip gaji per bulan (tersedia setelah HR approve payroll).

#### E. Cuti (`/leave`)
1. Klik **"+ Ajukan Cuti"**
2. Pilih jenis cuti, tanggal mulai–selesai, alasan
3. Upload lampiran (jika ada, misal surat dokter)
4. Submit → menunggu approval
5. Lihat status: Pending → Approved / Rejected

#### F. Lembur (`/overtime`)
1. Ajukan lembur: tanggal, jam mulai–selesai, alasan
2. Submit → menunggu approval manager

#### G. Koreksi Absensi (`/correction`)
Ajukan koreksi jika ada kesalahan absensi (jam masuk/pulang salah).Upload bukti pendukung jika perlu.

#### H. Profil Saya
- **Data Profil** (`/profile`): lihat data diri
- **Perubahan Profil** (`/profile/change-requests`): ajukan perubahan (alamat, no. darurat, dll)
- **Keamanan Akun** (`/security`): ganti password
- **Dokumen Saya** (`/documents`): lihat & download dokumen (kontrak, slip, dll)

---

## 4. Alur Bisnis Utama

### 4.1 Onboarding Karyawan Baru
```
Admin/HR buat akun → Karyawan login → Ganti password → Upload dokumen → Absen harian
```

### 4.2 Approval Cuti
```
Employee ajukan cuti → Manager approve/reject → HR review → Selesai
```
>ponytail: Saat ini single-approval (manager atau HR). Multi-stage approval pipeline di backend sudah ada di model, tapi UI approval timeline perlu di-hook ke frontend.

### 4.3 Shift Schedule → Publish
```
Admin/HR buat jadwal → Bulk assign / Rotating → Validasi konflik → Publish → Manager/Employee lihat di "Jadwal Saya"
```

### 4.4 Payroll Run
```
HR buat period → Adjustments (bonus/denda) → HR Approve → Lock Period → Slip Gaji generated → Employee download
```

### 4.5 Profile Change Request
```
Employee ajukan perubahan → HR/Admin review → Approve/Reject → Data diupdate
```

---

## 5. Troubleshooting

### Q: Login gagal terus?
1. Cek credential (capslock, spasi)
2. Backend running: `docker ps` → hris-backend harus "Up"
3. Token expired → logout → login ulang
4. Reset via database: `UPDATE users SET password = Hash::make('password123') WHERE email = '...'`

### Q: Jadwal Shift tidak muncul?
1. Cek apakah schedule sudah di-**Publish**
2. Pastikan role punya akses (`admin`, `hr`, `manager` hanya)
3. Refresh page / clear cache

### Q: Slip Gaji kosong?
1. Payroll period belum di-generate → HR harus run payroll
2. Period belum di-approve & locked
3. Slip baru available setelah period locked

### Q: Cuti tidak bisa diajukan?
1. Cek saldo cuti (`/leave` halaman utama)
2. Tanggal bentrok dengan shift schedule lain
3. Masa trial karyawan (jika berlaku)

### Q: Absen tidak bisa check-in?
1. Sudah check-in hari ini? Sistem hanya 1x per hari
2. Di luar window waktu shift (terlalu awal/terlalu malam)
3. Koreksi via `/correction`

### Q: Access denied di suatu halaman?
Role tidak punya akses ke modul tersebut. Hubungi admin untuk upgrade role.

---

## 6. Info Teknis

| Komponen | Value |
|---|---|
| Backend API | `http://43.157.207.183:8000/api/v1` |
| Frontend | `http://43.157.207.183:3000` |
| Database | PostgreSQL 16 (`hris`) |
| Container | Docker Compose |
| Auth | Laravel Sanctum (token-based) |
| Backup | Daily 3 AM, 7-day retention |
| Server SSH | `ubuntu@43.157.207.183` |

### Restart Services
```bash
cd /home/ubuntu/projects/hris-msr
docker compose restart backend   # API
docker compose restart frontend  # UI
docker compose restart postgres  # DB
```

### Logs
```bash
docker compose logs -f backend
docker compose logs -f frontend
```
