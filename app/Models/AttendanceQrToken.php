<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AttendanceQrToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'type',
        'expires_at',
        'used_at',
        'used_by',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function usedByEmployee()
    {
        return $this->belongsTo(Employee::class, 'used_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->lessThan(now());
    }

    public function isUsableFor(string $type): bool
    {
        return $this->is_active
            && ! $this->used_at
            && ! $this->isExpired()
            && in_array($this->type, [$type, 'both'], true);
    }

    public function markUsedBy(Employee $employee): void
    {
        $this->update([
            'used_at' => Carbon::now(),
            'used_by' => $employee->id,
            'is_active' => false,
        ]);
    }
}
