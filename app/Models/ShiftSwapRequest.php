<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftSwapRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'requester_id',
        'target_id',
        'requester_schedule_id',
        'target_schedule_id',
        'status',
        'reason',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    public function target()
    {
        return $this->belongsTo(Employee::class, 'target_id');
    }

    public function requesterSchedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'requester_schedule_id');
    }

    public function targetSchedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'target_schedule_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForEmployee($query, int $id)
    {
        return $query->where(function ($q) use ($id) {
            $q->where('requester_id', $id)->orWhere('target_id', $id);
        });
    }

    public function approve(User $reviewer, ?string $notes = null): self
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);
        return $this;
    }

    public function reject(User $reviewer, ?string $notes = null): self
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);
        return $this;
    }
}
