<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_number',
        'nik',
        'phone',
        'address',
        'birth_date',
        'gender',
        'department',
        'department_id',
        'position',
        'position_id',
        'branch_id',
        'manager_id',
        'join_date',
        'employment_type',
        'basic_salary',
        'bank_name',
        'bank_account',
        'photo',
        'face_image',
        'face_registered_at',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'face_registered_at' => 'datetime',
        'basic_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departmentMaster(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function positionMaster(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class)
            ->orderByDesc('is_primary')
            ->orderBy('name');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
    }

    public function profileChangeRequests(): HasMany
    {
        return $this->hasMany(EmployeeProfileChangeRequest::class)->latest();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function salaryProfiles(): HasMany
    {
        return $this->hasMany(EmployeeSalaryProfile::class)->latest('effective_from');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    public function todayShiftSchedule(): HasOne
    {
        return $this->hasOne(ShiftSchedule::class)->whereDate('schedule_date', today());
    }

    public function getFullNameAttribute(): string
    {
        return $this->user->name ?? '';
    }
}
