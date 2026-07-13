<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftScheduleVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_schedule_id',
        'version',
        'changes',
        'changed_by',
        'action',
        'notes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function shiftSchedule()
    {
        return $this->belongsTo(ShiftSchedule::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
