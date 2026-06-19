<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'is_recurring',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('date', $year);
    }

    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeIsHoliday($query, string $date): bool
    {
        $dateObj = Carbon::parse($date);
        $month = $dateObj->month;
        $day = $dateObj->day;

        return $query->where(function ($q) use ($date, $month, $day) {
            $q->whereDate('date', $date)
                ->orWhere(function ($q2) use ($month, $day) {
                    $q2->where('is_recurring', true)
                        ->whereMonth('date', $month)
                        ->whereDay('date', $day);
                });
        })->exists();
    }

    public static function isHolidayDate(string $date): bool
    {
        $dateObj = Carbon::parse($date);
        $month = $dateObj->month;
        $day = $dateObj->day;

        return static::query()->where(function ($q) use ($date, $month, $day) {
            $q->whereDate('date', $date)
                ->orWhere(function ($q2) use ($month, $day) {
                    $q2->where('is_recurring', true)
                        ->whereMonth('date', $month)
                        ->whereDay('date', $day);
                });
        })->exists();
    }
}
