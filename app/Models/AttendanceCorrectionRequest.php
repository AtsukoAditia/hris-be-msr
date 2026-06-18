<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_CHECK_IN = 'check_in';

    public const TYPE_CHECK_OUT = 'check_out';

    public const TYPE_BOTH = 'both';

    protected $fillable = [
        'employee_id',
        'attendance_id',
        'correction_date',
        'correction_type',
        'original_check_in',
        'original_check_out',
        'requested_check_in',
        'requested_check_out',
        'reason',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'status',
        'reviewed_by',
        'review_note',
        'old_values',
        'new_values',
        'reviewed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'correction_date' => 'date',
        'original_check_in' => 'datetime',
        'original_check_out' => 'datetime',
        'requested_check_in' => 'datetime',
        'requested_check_out' => 'datetime',
        'old_values' => 'array',
        'new_values' => 'array',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function affectsCheckIn(): bool
    {
        return in_array($this->correction_type, [self::TYPE_CHECK_IN, self::TYPE_BOTH], true);
    }

    public function affectsCheckOut(): bool
    {
        return in_array($this->correction_type, [self::TYPE_CHECK_OUT, self::TYPE_BOTH], true);
    }
}
