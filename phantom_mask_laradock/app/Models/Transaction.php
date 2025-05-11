<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /**
     * 可批量賦值的屬性
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'pharmacy_id',
        'mask_id',
        'transaction_amount',
        'transaction_date',
    ];

    /**
     * 應該被轉換為日期的屬性
     *
     * @var array
     */
    protected $casts = [
        'transaction_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 取得此交易記錄的用戶
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(PharmacyUser::class, 'user_id');
    }

    /**
     * 取得此交易記錄的藥局
     */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    /**
     * 取得此交易記錄的口罩產品
     */
    public function mask(): BelongsTo
    {
        return $this->belongsTo(Mask::class);
    }
}