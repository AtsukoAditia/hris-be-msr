<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'check_in_photo',
        'check_out_photo',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'status',
        'notes',
        'late_minutes',
        'overtime_minutes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'check_in_latitude' => 'decimal:8',
        'check_in_longitude' => 'decimal:8',
        'check_out_latitude' => 'decimal:8',
        'check_out_longitude' => 'decimal:8',
    ];

    // Relationship: attendance belongs to employee
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Relationship: attendance belongs to shift
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    // Scope: attendance by date range
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('attendance_date', [$start, $end]);
    }

    // Scope: today's attendance
    public function scopeToday($query)
    {
        return $query->whereDate('attendance_date', today());
    }
}
