# Employee–Department Migration

Dokumen ini menjelaskan masa transisi dari kolom teks `employees.department` ke relasi `employees.department_id`.

## Contract Baru

Employee API menerima `department_id` untuk create dan update:

```json
{
  "department_id": 1
}
```

Response Employee memuat:

```json
{
  "department_id": 1,
  "department": "IT",
  "department_code": "IT",
  "department_name": "Information Technology",
  "department_master": {
    "id": 1,
    "code": "IT",
    "name": "Information Technology"
  }
}
```

## Kompatibilitas Sementara

Payload lama masih diterima:

```json
{
  "department": "Operations"
}
```

Alias lama seperti `Human Resource`, `Operation`, `Management`, dan `Marketing` dipetakan ke master Department yang sesuai.

Kolom teks `employees.department` belum dihapus karena masih digunakan oleh frontend dan beberapa report. Setelah seluruh consumer menggunakan `department_id`, kolom legacy dapat dihapus melalui migration cleanup terpisah.

## Filter

```text
GET /api/v1/employees?department_id={id}
```

Filter lama `department={value}` masih didukung selama masa transisi.
