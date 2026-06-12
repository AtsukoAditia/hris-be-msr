<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'office_name',
        'office_latitude',
        'office_longitude',
        'radius_meters',
        'is_radius_enabled',
        'is_qr_enabled',
        'qr_expiry_minutes',
    ];

    protected $casts = [
        'office_latitude' => 'decimal:8',
        'office_longitude' => 'decimal:8',
        'radius_meters' => 'integer',
        'is_radius_enabled' => 'boolean',
        'is_qr_enabled' => 'boolean',
        'qr_expiry_minutes' => 'integer',
    ];

    public static function current(): self
    {
        return self::firstOrCreate([], [
            'office_name' => 'Main Office',
            'radius_meters' => 100,
            'is_radius_enabled' => false,
            'is_qr_enabled' => true,
            'qr_expiry_minutes' => 5,
        ]);
    }

    public function hasOfficeCoordinate(): bool
    {
        return !is_null($this->office_latitude) && !is_null($this->office_longitude);
    }
}
