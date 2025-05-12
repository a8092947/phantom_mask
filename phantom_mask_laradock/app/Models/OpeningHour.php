<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class OpeningHour extends Model
{
    protected $fillable = [
        'pharmacy_id',
        'day_of_week',
        'open_time',
        'close_time'
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'open_time' => 'datetime',
        'close_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 關聯定義
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    // 查詢範圍
    public function scopePharmacy(Builder $query, ?int $pharmacyId): Builder
    {
        if ($pharmacyId) {
            return $query->where('pharmacy_id', $pharmacyId);
        }
        return $query;
    }

    public function scopeDayOfWeek(Builder $query, ?int $dayOfWeek): Builder
    {
        if ($dayOfWeek !== null) {
            return $query->where('day_of_week', $dayOfWeek);
        }
        return $query;
    }

    public function scopeOpenAt(Builder $query, Carbon $dateTime): Builder
    {
        $dayOfWeek = $dateTime->dayOfWeek;
        $time = $dateTime->format('H:i:s');

        return $query->where('day_of_week', $dayOfWeek)
            ->where('open_time', '<=', $time)
            ->where('close_time', '>=', $time);
    }

    public function scopeOrderByDayOfWeek(Builder $query, string $order = 'asc'): Builder
    {
        return $query->orderBy('day_of_week', $order);
    }

    // 事件處理
    protected static function booted()
    {
        static::saving(function ($openingHour) {
            // 驗證星期幾
            if ($openingHour->day_of_week < 0 || $openingHour->day_of_week > 6) {
                throw new \Exception('星期幾必須在 0-6 之間');
            }

            // 確保時間格式正確
            if (!$openingHour->open_time instanceof Carbon) {
                $openingHour->open_time = Carbon::parse($openingHour->open_time);
            }
            if (!$openingHour->close_time instanceof Carbon) {
                $openingHour->close_time = Carbon::parse($openingHour->close_time);
            }
        });
    }
}