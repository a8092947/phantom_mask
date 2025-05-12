<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Mask extends Model
{
    protected $fillable = [
        'pharmacy_id',
        'name',
        'price',
        'stock'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 關聯定義
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // 查詢範圍
    public function scopePharmacy(Builder $query, ?int $pharmacyId): Builder
    {
        if ($pharmacyId) {
            return $query->where('pharmacy_id', $pharmacyId);
        }
        return $query;
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeOrderByPrice(Builder $query, string $order = 'asc'): Builder
    {
        return $query->orderBy('price', $order);
    }

    // 業務邏輯方法
    public function hasStock(int $quantity = 1): bool
    {
        return $this->stock >= $quantity;
    }

    public function decreaseStock(int $quantity = 1): bool
    {
        if (!$this->hasStock($quantity)) {
            return false;
        }
        $this->decrement('stock', $quantity);
        return true;
    }

    // 事件處理
    protected static function booted()
    {
        static::creating(function ($mask) {
            // 建立口罩時的處理邏輯
            if (!isset($mask->stock)) {
                $mask->stock = 0;
            }
        });

        static::updating(function ($mask) {
            // 更新口罩時的處理邏輯
            if ($mask->isDirty('price') && $mask->price < 0) {
                throw new \Exception('口罩價格不能為負數');
            }
            if ($mask->isDirty('stock') && $mask->stock < 0) {
                throw new \Exception('口罩庫存不能為負數');
            }
        });

        static::deleting(function ($mask) {
            // 刪除口罩時的處理邏輯
            if ($mask->transactions()->exists()) {
                throw new \Exception('無法刪除已有交易記錄的口罩');
            }
        });
    }
}