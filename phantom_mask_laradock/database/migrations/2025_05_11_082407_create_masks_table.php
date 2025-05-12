<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 口罩產品表設計說明：
     * 
     * 原始 JSON 格式：
     * "masks": [
     *   {
     *     "name": "True Barrier (green) (3 per pack)",
     *     "price": 13.7
     *   }
     * ]
     * 
     * 設計理念：
     * 1. 將口罩產品獨立成一個表，因為一個藥局可能有多個產品
     * 2. 使用外鍵關聯藥局，確保資料完整性
     * 3. 使用 decimal 型態儲存價格，確保精確度
     * 4. 產品名稱包含完整資訊（品牌、顏色、包裝數量）
     * 5. 【補充】stock 欄位為合理補足，代表庫存數量，防止超賣，讓購買流程更貼近真實商業邏輯。
     */
    public function up()
    {
        Schema::create('masks', function (Blueprint $table) {
            $table->id()->comment('口罩產品唯一識別碼');
            $table->foreignId('pharmacy_id')
                  ->constrained()
                  ->onDelete('cascade')
                  ->comment('關聯的藥局ID');
            $table->string('name')
                  ->comment('口罩產品名稱，包含品牌、顏色、包裝數量等資訊');
            $table->decimal('price', 10, 2)
                  ->comment('口罩產品價格');
            $table->integer('stock')
                  ->default(0)
                  ->comment('庫存數量，合理補足，防止超賣，讓購買流程更貼近真實商業邏輯');
            $table->timestamps();
            
            // 新增索引
            $table->index(['pharmacy_id', 'name']);
            $table->index('price');
            $table->index('stock');
        });

        // 使用原生 SQL 添加約束
        DB::statement('ALTER TABLE masks ADD CONSTRAINT chk_mask_price CHECK (price >= 0)');
        DB::statement('ALTER TABLE masks ADD CONSTRAINT chk_mask_stock CHECK (stock >= 0)');
    }

    public function down()
    {
        Schema::dropIfExists('masks');
    }
};