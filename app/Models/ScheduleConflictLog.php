<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleConflictLog extends Model
{
    use HasFactory;

    public const REST_HOUR = 'rest_hour';
    public const MAX_HOURS = 'max_hours';
    public const OVERLAP = 'overlap';
    public const COVERAGE = 'coverage';

    protected $fillable = [
        'employee_id',
        'schedule_date',
        'conflict_type',
        'conflict_message',
        'details',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'details' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
