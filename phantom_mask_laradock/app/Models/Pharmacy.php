<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pharmacy extends Model
{
    /**
     * 可批量賦值的屬性
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'cash_balance',
    ];

    /**
     * 應該被轉換為日期的屬性
     *
     * @var array
     */
    protected $casts = [
        'cash_balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 取得藥局的所有營業時間
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class);
    }

    /**
     * 取得藥局的所有口罩產品
     */
    public function masks(): HasMany
    {
        return $this->hasMany(Mask::class);
    }

    /**
     * 取得藥局的所有交易記錄
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}