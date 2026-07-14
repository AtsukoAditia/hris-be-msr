<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'module',
        'target_type',
        'target_id',
        'action',
        'method',
        'endpoint',
        'route_name',
        'response_status',
        'ip_address',
        'user_agent',
        'latitude',
        'longitude',
        'request_payload',
        'response_payload',
        'old_values',
        'new_values',
        'description',
        'logged_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'logged_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Compute human-readable diff lines.
     * Returns array of ['field' => string, 'old' => string, 'new' => string].
     */
    public function getDiffLines(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $lines = [];

        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            if ($oldVal === $newVal) continue;
            $lines[] = [
                'field' => $key,
                'old' => is_scalar($oldVal) ? (string) $oldVal : json_encode($oldVal),
                'new' => is_scalar($newVal) ? (string) $newVal : json_encode($newVal),
            ];
        }
        return $lines;
    }

    /**
     * Human-readable field name mapping.
     */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => 'Nama Lengkap',
            'email' => 'Email',
            'phone' => 'Telepon',
            'address' => 'Alamat',
            'department_id' => 'Departemen',
            'position_id' => 'Jabatan',
            'branch_id' => 'Cabang',
            'join_date' => 'Tanggal Masuk',
            'status' => 'Status',
            'salary' => 'Gaji',
            'leave_type_id' => 'Tipe Cuti',
            'start_date' => 'Tanggal Mulai',
            'end_date' => 'Tanggal Selesai',
            'reason' => 'Alasan',
            'notes' => 'Catatan',
            'shift_id' => 'Shift',
            'schedule_date' => 'Tanggal Jadwal',
            'is_active' => 'Status Aktif',
            'employment_type' => 'Tipe Kontrak',
            'emergency_contact_name' => 'Nama Kontak Darurat',
            'emergency_contact_phone' => 'Telepon Darurat',
            'relationship' => 'Hubungan',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function target(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
