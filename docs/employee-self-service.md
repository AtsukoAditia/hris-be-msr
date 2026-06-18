# Employee Self-Service Backend

## Scope

Backend Employee Self-Service memisahkan perubahan profil menjadi dua jalur:

1. **Direct update** untuk data kontak dan domisili yang dapat diubah Employee sendiri.
2. **Profile change request** untuk data legal, identitas, dan benefit yang harus direview Admin/HR.

Change password tetap memakai endpoint authentication yang sudah tersedia:

```http
POST /api/v1/auth/change-password
```

## Field Policy

### Direct Self-Service Fields

Employee dapat memperbarui field berikut melalui `PATCH /api/v1/profile/me`:

```text
phone
address
personal_email
alternate_phone
domicile_address
city
province
postal_code
```

Jika payload juga membawa field sensitif dengan nilai yang tidak berubah, request tetap dapat diproses untuk menjaga kompatibilitas form lama. Perubahan nilai sensitif akan menghasilkan `422`.

### Approval-Required Fields

```text
birth_date
gender
place_of_birth
marital_status
blood_type
religion
nationality
identity_address
tax_number
social_security_number
health_insurance_number
```

## Employee Endpoints

```http
GET    /api/v1/profile/change-requests
POST   /api/v1/profile/change-requests
GET    /api/v1/profile/change-requests/{profileChangeRequest}
DELETE /api/v1/profile/change-requests/{profileChangeRequest}
```

### Create Request

```json
{
  "changes": {
    "birth_date": "1995-02-11",
    "nationality": "Indonesia",
    "tax_number": "TAX-2026-001"
  },
  "reason": "Dokumen legal terbaru telah diterbitkan."
}
```

Aturan:

- Hanya field approval-required yang diterima.
- Nilai baru harus berbeda dari nilai saat ini.
- Maksimal satu request `pending` per Employee.
- Employee hanya dapat melihat dan membatalkan request miliknya sendiri.
- Pembatalan mengubah status menjadi `cancelled`; row tidak dihapus.

Filter list:

```text
status
date_from
date_to
sort=newest|oldest
per_page=1..100
```

## Admin/HR Endpoints

```http
GET  /api/v1/profile-change-requests
GET  /api/v1/profile-change-requests/{profileChangeRequest}
POST /api/v1/profile-change-requests/{profileChangeRequest}/approve
POST /api/v1/profile-change-requests/{profileChangeRequest}/reject
```

Filter Admin/HR:

```text
status
employee_id
search
date_from
date_to
sort=newest|oldest
per_page=1..100
```

`search` mencocokkan nama, work email, dan employee number.

### Approve

```json
{
  "review_note": "Dokumen pendukung telah diverifikasi."
}
```

Approval:

1. Mengunci request dan Employee row.
2. Memastikan status masih `pending`.
3. Menolak reviewer yang sama dengan requester.
4. Memvalidasi ulang format dan uniqueness.
5. Membandingkan data saat ini dengan snapshot saat request dibuat.
6. Menerapkan perubahan dalam database transaction.
7. Menyimpan reviewer, note, dan `reviewed_at`.

Jika profil berubah setelah request dibuat, approval ditolak agar request lama tidak menimpa data terbaru.

### Reject

```json
{
  "review_note": "Dokumen pendukung belum lengkap."
}
```

`review_note` wajib untuk rejection. Profile Employee tidak berubah.

## Status

```text
pending
approved
rejected
cancelled
```

Status yang sudah diproses tidak dapat diproses ulang.

## Response Shape

```json
{
  "id": 10,
  "employee_id": 7,
  "status": "pending",
  "reason": "Dokumen legal terbaru telah diterbitkan.",
  "review_note": null,
  "current_values": {
    "nationality": "Indonesia"
  },
  "requested_changes": {
    "nationality": "Malaysia"
  },
  "changes": [
    {
      "field": "nationality",
      "current_value": "Indonesia",
      "requested_value": "Malaysia"
    }
  ],
  "employee": {
    "id": 7,
    "employee_number": "EMP-0007",
    "name": "Employee Name",
    "work_email": "employee@example.com"
  },
  "requester": {},
  "reviewer": null,
  "can_cancel": true,
  "can_review": true,
  "created_at": "2026-06-18T10:00:00.000000Z"
}
```

`can_cancel` dan `can_review` menunjukkan eligibility berdasarkan status. Authorization role tetap diterapkan oleh route dan ownership checks.

## Data Model

Table: `employee_profile_change_requests`

```text
employee_id
requested_by
reviewed_by
current_values JSON
requested_changes JSON
reason
status
review_note
reviewed_at
cancelled_at
created_at
updated_at
```

Snapshot old/new values disimpan untuk audit dan stale-data protection.

## Security and Authorization

- Semua endpoint membutuhkan Sanctum authentication.
- Employee hanya dapat mengakses request miliknya.
- Admin dan HR dapat review semua request.
- Manager tidak dapat memakai review endpoint.
- Reviewer tidak dapat memproses request miliknya sendiri.
- Field organisasi dan employment tidak tersedia dalam self-service change request.
- Unique identifiers divalidasi saat submit dan divalidasi ulang saat approval.

## Tests

Coverage backend mencakup:

- Direct field update.
- Sensitive direct update rejection.
- Request create/list/show/cancel.
- Ownership protection.
- One-pending-request rule.
- Validation dan unique identifiers.
- Admin/HR role access.
- Manager rejection.
- Approval transaction.
- Required rejection note.
- Self-review protection.
- Duplicate processing protection.
- Stale snapshot protection.
- Approval-time uniqueness revalidation.
