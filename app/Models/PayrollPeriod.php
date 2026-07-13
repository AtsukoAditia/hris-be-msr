<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cutoff_start_date' => 'date',
        'cutoff_end_date' => 'date',
        'locked_at' => 'datetime',
    ];

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function scopeLocked($query)
    {
        return $query->whereNotNull('locked_at');
    }

    public function scopeUnlocked($query)
    {
        return $query->whereNull('locked_at');
    }

    public function lock(User $user): self
    {
        $this->update([
            'locked_at' => now(),
            'locked_by' => $user->id,
        ]);
        return $this;
    }

    public function unlock(): self
    {
        $this->update([
            'locked_at' => null,
            'locked_by' => null,
        ]);
        return $this;
    }
}
