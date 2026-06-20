<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimePolicy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'daily_max_minutes',
        'weekly_max_minutes',
        'rate_multiplier',
        'is_active',
    ];

    protected $casts = [
        'daily_max_minutes' => 'integer',
        'weekly_max_minutes' => 'integer',
        'rate_multiplier' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function overtimeRequests()
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}