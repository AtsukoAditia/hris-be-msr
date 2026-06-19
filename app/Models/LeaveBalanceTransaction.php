<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalanceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_balance_id',
        'change',
        'balance_before',
        'balance_after',
        'transaction_type',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'change' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
    ];

    public function leaveBalance()
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo('reference');
    }
}
