<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mask extends Model
{
    /**
     * 可批量賦值的屬性
     *
     * @var array
     */
    protected $fillable = [
        'pharmacy_id',
        'name',
        'price',
    ];

    /**
     * 應該被轉換為日期的屬性
     *
     * @var array
     */
    protected $casts = [
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 取得此口罩產品所屬的藥局
     */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    /**
     * 取得此口罩產品的所有交易記錄
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}