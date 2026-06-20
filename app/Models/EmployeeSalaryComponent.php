<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryComponent extends Model
{
    use HasFactory;

    protected $fillable = ['employee_salary_profile_id', 'salary_component_id', 'amount', 'percentage', 'formula'];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:4',
    ];

    public function salaryProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryProfile::class, 'employee_salary_profile_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}
