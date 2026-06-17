<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'personal_email',
        'alternate_phone',
        'place_of_birth',
        'marital_status',
        'blood_type',
        'religion',
        'nationality',
        'identity_address',
        'domicile_address',
        'city',
        'province',
        'postal_code',
        'tax_number',
        'social_security_number',
        'health_insurance_number',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
