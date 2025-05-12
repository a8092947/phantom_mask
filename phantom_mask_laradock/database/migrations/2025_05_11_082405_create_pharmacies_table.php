<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 藥局資料表設計說明：
     * 
     * 原始 JSON 格式：
     * {
     *   "name": "DFW Wellness",
     *   "cashBalance": 328.41,
     *   "openingHours": "Mon, Wed, Fri 08:00 - 12:00 / Tue, Thur 14:00 - 18:00",
     *   "masks": [
     *     {
     *       "name": "True Barrier (green) (3 per pack)",
     *       "price": 13.7
     *     }
     *   ]
     * }
     * 
     * 設計理念：
     * 1. 將藥局基本資訊獨立成一個表
     * 2. 營業時間因為有多個時段，所以獨立成一個表
     * 3. 口罩產品因為一個藥局可能有多個，所以獨立成一個表
     * 4. 使用 decimal 型態儲存金額，確保精確度
     */
    public function up()
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id()->comment('藥局唯一識別碼');
            $table->string('name')->unique()->comment('藥局名稱');
            $table->decimal('cash_balance', 10, 2)
                  ->default(0)
                  ->comment('藥局現金餘額，用於追蹤藥局的收入');
            $table->timestamps();
            
            // 新增索引
            $table->index('name');
            $table->index('cash_balance');
        });

        // 使用原生 SQL 添加約束
        DB::statement('ALTER TABLE pharmacies ADD CONSTRAINT chk_pharmacy_cash_balance CHECK (cash_balance >= 0)');
    }

    public function down()
    {
        Schema::dropIfExists('pharmacies');
    }
};