# Smart Attendance HRIS — Backend Documentation

Dokumentasi ini adalah indeks teknis untuk repository backend `hris-be-msr`.

## Document Index

| Document | Purpose |
|---|---|
| [PROJECT_STATUS.md](PROJECT_STATUS.md) | Baseline implementasi aktual, gap, dan milestone aktif |
| [MODULES.md](MODULES.md) | Inventaris modul HRIS, fitur, akses, dan status |
| [ROADMAP.md](ROADMAP.md) | Urutan pengembangan setelah core HRIS |
| [API_MATRIX.md](API_MATRIX.md) | Endpoint, role access, dan tujuan API |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Struktur aplikasi, aliran request, data, dan security boundary |
| [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) | Setup, aturan implementasi, testing, dan Definition of Done |
| [employee-self-service.md](employee-self-service.md) | Detail Employee Self-Service |
| [employee-document-management.md](employee-document-management.md) | Detail pengelolaan dokumen employee |
| [employee-profile-emergency-contact.md](employee-profile-emergency-contact.md) | Detail profile dan emergency contact |

## Source-of-Truth Order

Ketika terdapat perbedaan dokumentasi, gunakan urutan berikut:

1. `routes/api_v1.php` untuk endpoint aktif.
2. Migration dan model untuk struktur data aktual.
3. Form Request, policy, middleware, service, dan controller untuk business rule.
4. Automated tests untuk expected behavior.
5. Dokumen dalam folder ini untuk ringkasan dan rencana.

## Documentation Rules

Setiap modul baru atau perubahan kontrak API harus memperbarui:

- `PROJECT_STATUS.md`
- `MODULES.md`
- `ROADMAP.md`
- `API_MATRIX.md`
- Dokumen frontend yang berhubungan

Jangan menandai modul sebagai selesai hanya karena endpoint telah tersedia. Modul harus memenuhi Definition of Done pada `DEVELOPMENT_GUIDE.md`.

## Current Milestone

**Basic Payroll Foundation** merupakan fokus berikutnya setelah Overtime Management selesai end-to-end.
