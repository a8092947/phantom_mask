<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 用戶表設計說明：
     * 
     * 原始 JSON 格式：
     * {
     *   "name": "Yvonne Guerrero",
     *   "cashBalance": 191.83,
     *   "purchaseHistories": [
     *     {
     *       "pharmacyName": "Keystone Pharmacy",
     *       "maskName": "True Barrier (green) (3 per pack)",
     *       "transactionAmount": 12.35,
     *       "transactionDate": "2021-01-04 15:18:51"
     *     }
     *   ]
     * }
     * 
     * 設計理念：
     * 1. 將用戶基本資訊獨立成一個表
     * 2. 購買歷史因為包含多筆交易，所以獨立成一個表
     * 3. 使用 decimal 型態儲存金額，確保精確度
     * 4. 使用 timestamps 追蹤資料異動時間
     */
    public function up()
    {
        Schema::create('pharmacy_users', function (Blueprint $table) {
            $table->id()->comment('用戶唯一識別碼');
            $table->string('name')->comment('用戶姓名');
            $table->decimal('cash_balance', 10, 2)
                  ->default(0)
                  ->comment('用戶現金餘額，用於追蹤用戶的消費');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pharmacy_users');
    }
};