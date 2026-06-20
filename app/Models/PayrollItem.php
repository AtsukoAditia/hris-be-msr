<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id', 'salary_component_id', 'code', 'name', 'type',
        'source', 'quantity', 'rate', 'amount', 'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}
