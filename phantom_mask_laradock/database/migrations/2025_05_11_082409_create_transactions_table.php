<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 交易記錄表設計說明：
     * 
     * 原始 JSON 格式：
     * "purchaseHistories": [
     *   {
     *     "pharmacyName": "Keystone Pharmacy",
     *     "maskName": "True Barrier (green) (3 per pack)",
     *     "transactionAmount": 12.35,
     *     "transactionDate": "2021-01-04 15:18:51"
     *   }
     * ]
     * 
     * 設計理念：
     * 1. 將交易記錄獨立成一個表，方便追蹤所有交易
     * 2. 使用外鍵關聯用戶、藥局和口罩產品，確保資料完整性
     * 3. 使用 decimal 型態儲存交易金額，確保精確度
     * 4. 使用 timestamp 型態儲存交易時間，方便查詢特定時間範圍的交易
     * 5. 使用 timestamps 追蹤資料異動時間
     * 
     * 資料完整性考慮：
     * 1. 當刪除用戶時，相關交易記錄也會被刪除（cascade）
     * 2. 當刪除藥局時，相關交易記錄也會被刪除（cascade）
     * 3. 當刪除口罩產品時，相關交易記錄也會被刪除（cascade）
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id()->comment('交易記錄唯一識別碼');
            $table->foreignId('user_id')
                  ->constrained('pharmacy_users')
                  ->onDelete('cascade')
                  ->comment('關聯的用戶ID');
            $table->foreignId('pharmacy_id')
                  ->constrained('pharmacies')
                  ->onDelete('cascade')
                  ->comment('關聯的藥局ID');
            $table->foreignId('mask_id')
                  ->constrained('masks')
                  ->onDelete('cascade')
                  ->comment('關聯的口罩產品ID');
            $table->decimal('transaction_amount', 10, 2)
                  ->comment('交易金額');
            $table->timestamp('transaction_date')
                  ->comment('交易時間');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};