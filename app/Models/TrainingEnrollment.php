<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingEnrollment extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'registered' => 'Terdaftar',
        'confirmed' => 'Dikonfirmasi',
        'attended' => 'Hadir',
        'completed' => 'Selesai',
        'no_show' => 'Tidak Hadir',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'training_id',
        'employee_id',
        'status',
        'notes',
        'feedback',
        'score',
        'certificate_issued',
        'enrolled_at',
        'completed_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'certificate_issued' => 'boolean',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
