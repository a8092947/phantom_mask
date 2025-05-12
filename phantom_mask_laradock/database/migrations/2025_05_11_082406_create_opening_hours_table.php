<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 營業時間表設計說明：
     * 
     * 原始 JSON 格式：
     * "openingHours": "Mon, Wed, Fri 08:00 - 12:00 / Tue, Thur 14:00 - 18:00"
     * 
     * 設計理念：
     * 1. 將複雜的營業時間字串拆分成多筆記錄
     * 2. 每個時段獨立成一筆記錄，方便查詢特定時間是否營業
     * 3. 使用 time 型態儲存時間，確保時間格式正確
     * 4. 使用 day_of_week 儲存星期幾，方便查詢特定日期的營業時間
     * 5. 使用外鍵關聯藥局，確保資料完整性
     */
    public function up()
    {
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id()->comment('營業時間唯一識別碼');
            $table->foreignId('pharmacy_id')
                  ->constrained()
                  ->onDelete('cascade')
                  ->comment('關聯的藥局ID');
            $table->tinyInteger('day_of_week')
                  ->comment('星期幾 (0-6)');
            $table->time('open_time')
                  ->comment('開始營業時間');
            $table->time('close_time')
                  ->comment('結束營業時間');
            $table->timestamps();
            
            // 新增索引
            $table->index(['pharmacy_id', 'day_of_week']);
            $table->index(['day_of_week', 'open_time', 'close_time']);
            
            // 新增唯一約束，確保同一藥局在同一天不會有重複的營業時間
            $table->unique(['pharmacy_id', 'day_of_week', 'open_time', 'close_time'], 'opening_hours_unique');
        });

        // 使用原生 SQL 添加約束
        DB::statement('ALTER TABLE opening_hours ADD CONSTRAINT chk_day_of_week CHECK (day_of_week >= 0 AND day_of_week <= 6)');
        DB::statement('ALTER TABLE opening_hours ADD CONSTRAINT chk_time CHECK (open_time < close_time)');
    }

    public function down()
    {
        Schema::dropIfExists('opening_hours');
    }
};