<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'overtime_policy_id',
        'approved_by',
        'overtime_date',
        'planned_start_time',
        'planned_end_time',
        'planned_minutes',
        'actual_minutes',
        'rate_multiplier',
        'status',
        'reason',
        'attachment',
        'rejection_reason',
        'approved_at',
    ];

    protected $casts = [
        'overtime_date' => 'date',
        'planned_start_time' => 'string',
        'planned_end_time' => 'string',
        'planned_minutes' => 'integer',
        'actual_minutes' => 'integer',
        'rate_multiplier' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function overtimePolicy()
    {
        return $this->belongsTo(OvertimePolicy::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true);
    }
}
