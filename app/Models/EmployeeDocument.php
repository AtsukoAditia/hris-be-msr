<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class EmployeeDocument extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORY_LABELS = [
        'identity' => 'Identitas',
        'tax' => 'Perpajakan',
        'employment' => 'Kepegawaian',
        'education' => 'Pendidikan',
        'certification' => 'Sertifikasi',
        'medical' => 'Kesehatan',
        'payroll' => 'Payroll',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'employee_id',
        'uploaded_by',
        'category',
        'title',
        'description',
        'labels',
        'disk',
        'file_path',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'version',
        'issue_date',
        'expiry_date',
        'is_confidential',
    ];

    protected $casts = [
        'labels' => 'array',
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_confidential' => 'boolean',
        'version' => 'integer',
        'size_bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (EmployeeDocument $document): void {
            if ($document->file_path && Storage::disk($document->disk)->exists($document->file_path)) {
                Storage::disk($document->disk)->delete($document->file_path);
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? ucfirst($this->category);
    }

    public function expiryStatus(int $warningDays = 30): string
    {
        if (! $this->expiry_date) {
            return 'without_expiry';
        }

        $today = Carbon::today();

        if ($this->expiry_date->lt($today)) {
            return 'expired';
        }

        if ($this->expiry_date->lte($today->copy()->addDays($warningDays))) {
            return 'expiring';
        }

        return 'valid';
    }

    public function daysUntilExpiry(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return Carbon::today()->diffInDays($this->expiry_date, false);
    }
}
