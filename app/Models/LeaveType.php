<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory, SoftDeletes;

    const GENDER_ALL = 'all';

    const GENDER_MALE = 'male';

    const GENDER_FEMALE = 'female';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_paid',
        'requires_attachment',
        'max_consecutive_days',
        'min_service_months',
        'gender_restriction',
        'requires_balance',
        'max_days_per_year',
        'default_days_per_year',
        'carry_forward_enabled',
        'max_carry_forward_days',
        'is_active',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_attachment' => 'boolean',
        'requires_balance' => 'boolean',
        'carry_forward_enabled' => 'boolean',
        'is_active' => 'boolean',
        'max_consecutive_days' => 'integer',
        'min_service_months' => 'integer',
        'max_days_per_year' => 'integer',
        'default_days_per_year' => 'integer',
        'max_carry_forward_days' => 'integer',
    ];

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function policies()
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isEligibleForGender(?string $gender): bool
    {
        // NULL and 'all' both mean "no gender restriction".
        if ($this->gender_restriction === null || $this->gender_restriction === self::GENDER_ALL) {
            return true;
        }

        if ($gender === null) {
            return false;
        }

        return $this->gender_restriction === $gender;
    }
}
