<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'start_time',
        'end_time',
        'late_tolerance',
        'is_overnight',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_overnight' => 'boolean',
    ];

    public function shiftSchedules()
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
