<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Pharmacy extends Model
{
    protected $fillable = [
        'name',
        'cash_balance',
    ];

    protected $casts = [
        'cash_balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 關聯定義
    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class);
    }

    public function masks(): HasMany
    {
        return $this->hasMany(Mask::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // 查詢範圍
    public function scopeOpenAt(Builder $query, Carbon $dateTime): Builder
    {
        $dayOfWeek = $dateTime->dayOfWeek;
        $time = $dateTime->format('H:i:s');

        return $query->whereHas('openingHours', function ($q) use ($dayOfWeek, $time) {
            $q->where('day_of_week', $dayOfWeek)
              ->where('open_time', '<=', $time)
              ->where('close_time', '>=', $time);
        });
    }

    public function scopePriceRange(Builder $query, ?float $minPrice, ?float $maxPrice): Builder
    {
        if ($minPrice || $maxPrice) {
            return $query->whereHas('masks', function ($q) use ($minPrice, $maxPrice) {
                if ($minPrice) {
                    $q->where('price', '>=', $minPrice);
                }
                if ($maxPrice) {
                    $q->where('price', '<=', $maxPrice);
                }
            });
        }
        return $query;
    }

    // 業務邏輯方法
    public function canSellMask(int $maskId, float $amount): bool
    {
        $mask = $this->masks()->find($maskId);
        return $mask && $mask->price === $amount;
    }

    public function updateCashBalance(float $amount): void
    {
        $this->increment('cash_balance', $amount);
    }

    // 事件處理
    protected static function booted()
    {
        static::deleting(function ($pharmacy) {
            // 刪除藥局時的處理邏輯
            $pharmacy->masks()->delete();
            $pharmacy->openingHours()->delete();
            $pharmacy->transactions()->delete();
        });
    }
}