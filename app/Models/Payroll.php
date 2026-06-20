<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'employee_salary_profile_id',
        'status',
        'currency',
        'basic_salary',
        'total_earnings',
        'total_deductions',
        'net_salary',
        'attendance_days',
        'absent_days',
        'late_minutes',
        'unpaid_leave_days',
        'overtime_minutes',
        'input_snapshot',
        'generated_by',
        'reviewed_by',
        'finalized_by',
        'paid_by',
        'cancelled_by',
        'generated_at',
        'reviewed_at',
        'finalized_at',
        'paid_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'input_snapshot' => 'array',
        'generated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'finalized_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryProfile::class, 'employee_salary_profile_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->orderBy('type')->orderBy('id');
    }
}
