<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'position',
        'join_date',
        'employment_type',
        'basic_salary',
        'bank_name',
        'bank_account',
        'photo',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'basic_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->user->name ?? '';
    }
}
