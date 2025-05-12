<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'pharmacy_id',
        'mask_id',
        'amount',
        'quantity',
        'transaction_date'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'integer',
        'transaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 關聯定義
    public function user(): BelongsTo
    {
        return $this->belongsTo(PharmacyUser::class, 'user_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function mask(): BelongsTo
    {
        return $this->belongsTo(Mask::class);
    }

    // 查詢範圍
    public function scopeUser(Builder $query, ?int $userId): Builder
    {
        if ($userId) {
            return $query->where('user_id', $userId);
        }
        return $query;
    }

    public function scopeDateRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }
        return $query;
    }

    public function scopeOrderByAmount(Builder $query, string $order = 'desc'): Builder
    {
        return $query->orderBy('amount', $order);
    }

    // 業務邏輯方法
    public function processTransaction(): bool
    {
        // 檢查用戶餘額是否足夠
        if ($this->user->cash_balance < $this->amount) {
            throw new \Exception('用戶餘額不足');
        }

        // 檢查口罩庫存是否足夠
        if (!$this->mask->hasStock($this->quantity)) {
            throw new \Exception('口罩庫存不足');
        }

        // 使用資料庫交易確保資料一致性
        return DB::transaction(function () {
            // 扣除用戶餘額
            $this->user->decrement('cash_balance', $this->amount);
            
            // 增加藥局餘額
            $this->pharmacy->increment('cash_balance', $this->amount);
            
            // 減少口罩庫存
            $this->mask->decreaseStock($this->quantity);
            
            return true;
        });
    }

    // 事件處理
    protected static function booted()
    {
        static::creating(function ($transaction) {
            // 設定交易時間
            if (!isset($transaction->transaction_date)) {
                $transaction->transaction_date = Carbon::now();
            }
        });

        static::saving(function ($transaction) {
            // 驗證交易金額
            if ($transaction->amount <= 0) {
                throw new \Exception('交易金額必須大於 0');
            }

            // 驗證購買數量
            if ($transaction->quantity <= 0) {
                throw new \Exception('購買數量必須大於 0');
            }
        });
    }
}