<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Training extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'technical' => 'Teknis',
        'soft_skill' => 'Soft Skill',
        'compliance' => 'Kepatuhan',
        'leadership' => 'Kepemimpinan',
        'safety' => 'Keselamatan',
        'onboarding' => 'Onboarding',
    ];

    protected $fillable = [
        'title',
        'description',
        'category',
        'trainer',
        'location',
        'mode',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'max_participants',
        'cost',
        'status',
        'requirements',
        'certificate_template',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'max_participants' => 'integer',
        'cost' => 'integer',
    ];

    public function enrollments()
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(TrainingEnrollment::class)
            ->whereNotIn('status', ['cancelled']);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open'
            && $this->activeEnrollments()->count() < $this->max_participants
            && $this->start_date->isFuture();
    }

    public function getAvailableSlots(): int
    {
        return max(0, $this->max_participants - $this->activeEnrollments()->count());
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
