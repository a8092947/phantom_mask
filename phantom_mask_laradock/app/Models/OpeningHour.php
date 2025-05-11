<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningHour extends Model
{
    /**
     * 可批量賦值的屬性
     *
     * @var array
     */
    protected $fillable = [
        'pharmacy_id',
        'day_of_week',
        'open_time',
        'close_time',
    ];

    /**
     * 應該被轉換為日期的屬性
     *
     * @var array
     */
    protected $casts = [
        'open_time' => 'datetime',
        'close_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 取得此營業時間所屬的藥局
     */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }
}