<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'date',
        'check_in',
        'check_out',
        'check_in_lat',
        'check_in_lng',
        'check_out_lat',
        'check_out_lng',
        'status',
        'notes',
        'overtime_hours',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'check_in_lat' => 'decimal:8',
        'check_in_lng' => 'decimal:8',
        'check_out_lat' => 'decimal:8',
        'check_out_lng' => 'decimal:8',
        'overtime_hours' => 'decimal:2',
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
        return $query->whereBetween('date', [$start, $end]);
    }

    // Scope: today's attendance
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }
}
