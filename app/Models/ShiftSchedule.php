<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'schedule_date',
        'is_day_off',
        'notes',
        'created_by',
        'status',
        'conflict_type',
        'conflict_message',
        'version',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'is_day_off' => 'boolean',
        'published_at' => 'datetime',
        'version' => 'integer',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public function publish(User $user): self
    {
        $currentVersion = $this->version ?? 0;
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_by' => $user->id,
            'published_at' => now(),
            'version' => $currentVersion + 1,
        ]);

        return $this;
    }

    public function unpublish(): self
    {
        $this->update([
            'status' => self::STATUS_DRAFT,
            'published_by' => null,
            'published_at' => null,
        ]);

        return $this;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions()
    {
        return $this->hasMany(ShiftScheduleVersion::class);
    }
}
