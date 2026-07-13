<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdjustment extends Model
{
    protected $fillable = [
        'payroll_id',
        'type',
        'code',
        'name',
        'amount',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
