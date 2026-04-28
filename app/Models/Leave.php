<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'approved_by',
        'leave_type',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'rejection_reason',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'total_days' => 'integer',
    ];

    // Relationship: leave belongs to employee
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Relationship: leave approved by user
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scope: pending leaves
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // Scope: approved leaves
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    // Check if leave is pending
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
