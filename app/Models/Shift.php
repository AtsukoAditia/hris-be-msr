<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'break_duration',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    // Relationship: shift has many employees
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    // Relationship: shift has many attendances
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // Scope: active shifts only
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
