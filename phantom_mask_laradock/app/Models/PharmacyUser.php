<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PharmacyUser extends Model
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
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    // 查詢範圍
    public function scopeOrderByBalance(Builder $query, string $order = 'desc'): Builder
    {
        return $query->orderBy('cash_balance', $order);
    }

    // 事件處理
    protected static function booted()
    {
        static::creating(function ($user) {
            // 建立用戶時的處理邏輯
            $user->cash_balance = 0;
        });

        static::deleting(function ($user) {
            // 刪除用戶時的處理邏輯
            $user->transactions()->delete();
        });
    }
}