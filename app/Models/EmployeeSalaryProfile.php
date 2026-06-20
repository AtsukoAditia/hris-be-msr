<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSalaryProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'basic_salary', 'currency', 'effective_from',
        'effective_to', 'is_active', 'notes',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function scopeEffectiveFor(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $endDate)
            ->where(function (Builder $builder) use ($startDate) {
                $builder->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $startDate);
            });
    }
}
