<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'cutoff_start_date',
        'cutoff_end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cutoff_start_date' => 'date',
        'cutoff_end_date' => 'date',
    ];

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }
}
