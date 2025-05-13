# Response

## A. 基本資訊
### A.1. 技術選擇
- 使用 Laravel 框架 (版本 10.x)
- 使用 MySQL 資料庫
- 使用 Laradock 作為開發與部屬環境
- 部屬網址 （swagger）：https://phantom_mask_laradock.kdan.succ.work/api/documentation#/
- 輔助工具：Cursor

### A.2. 需求完成度
1. 列出特定時間營業的藥局 (List all pharmacies open at a specific time and on a day of the week if requested)
   - 實作於 `PharmacyController@index`
   - 支援時間和星期幾查詢
   - 使用資料庫索引優化查詢效能
   - API 路徑：`GET /api/pharmacies`
   - 範例請求：
     ```
     GET /api/pharmacies?day=Tuesday&time=10:00
     ```

2. 列出藥局販售的口罩 (List all masks sold by a given pharmacy, sorted by mask name or price)
   - 實作於 `PharmacyController@show`
   - 支援名稱和價格排序
   - 實作分頁功能
   - API 路徑：`GET /api/pharmacies/{id}`
   - 範例請求：
     ```
     GET /api/pharmacies/1?sort=name,price&order=asc,desc
     ```

3. 列出特定價格範圍的藥局 (List all pharmacies with more or less than x mask products within a price range)
   - 實作於 `PharmacyController@index`
   - 支援價格範圍和數量條件
   - 使用資料庫聚合函數
   - API 路徑：`GET /api/pharmacies`
   - 範例請求：
     ```
     GET /api/pharmacies?min_price=10&max_price=20&mask_count=5
     ```

4. 列出交易金額最高的用戶 (The top x users by total transaction amount of masks within a date range)
   - 實作於 `UserController@getTopUsers`
   - 支援日期範圍查詢
   - 使用資料庫聚合函數
   - API 路徑：`GET /api/users/top`
   - 範例請求：
     ```
     GET /api/transactions/top-users?limit=10&sort_by=amount&order_by=asc
     ```

5. 列出交易統計 (The total number of masks and dollar value of transactions within a date range)
   - 實作於 `TransactionController@getStatistics`
   - 支援日期範圍查詢
   - 使用資料庫聚合函數
   - API 路徑：`GET /api/transactions/statistics`
   - 範例請求：
     ```
     GET /api/transactions/statistics?start_date=2021-01-01&end_date=2021-01-31
     ```

6. 搜尋功能 (Search for pharmacies or masks by name, ranked by relevance to the search term)
   - 實作於 `PharmacyController@index`
   - 使用全文搜尋
   - 實作相關性排序
   - API 路徑：`GET /api/pharmacies`
   - 範例請求：
     ```
     GET /api/pharmacies?q=pharmacy
     ```

7. 購買交易 (Process a user purchases a mask from a pharmacy, and handle all relevant data changes in an atomic transaction)
   - 實作於 `TransactionController@purchase`
   - 使用資料庫交易確保原子性
   - 實作庫存和餘額檢查
   - API 路徑：`POST /api/transactions`
   - 範例請求：
     ```
     POST /api/transactions
     {
         "user_id": 1,
         "pharmacy_id": 1,
         "mask_id": 1,
         "quantity": 1
     }
     ```

### A.3. 程式碼品質
1. 遵循 Laravel 最佳實踐
   - 使用 Repository 模式
   - 使用 Service 層處理業務邏輯
   - 使用 Form Request 驗證

2. 資料庫設計
   - 使用適當的索引
   - 使用外鍵約束
   - 使用資料庫交易
   - 參考檔案：
     - `database/migrations/2025_05_11_082404_create_users_table.php`
     - `database/migrations/2025_05_11_082405_create_pharmacies_table.php`
     - `database/migrations/2025_05_11_082406_create_masks_table.php`
     - `database/migrations/2025_05_11_082407_create_transactions_table.php`

3. 錯誤處理
   - 使用 Exception Handler
   - 統一的錯誤回應格式
   - 詳細的錯誤訊息

### A.4. API 文件
1. 使用 OpenAPI 規格
  - 參考Swagger UI 路徑：`phantom_mask_laradock/storage/api-docs/api-docs.json`
2. 提供 Postman 集合與自動測試報告
  - postman匯出檔案：`docs/Phantom Mask API.postman_collection.json`
  - 測試報告參考截圖：`docs/postman-test.png`
3. 提供 Postman 執行後的具體的內容
  - 前端可直接對照範例參考截圖：`docs/postman-api-example/` 目錄下的所有檔案


### A.5. 資料匯入
1. 使用 Artisan 命令
2. 支援資料驗證
3. 支援錯誤處理
4. 指令說明請參考 [commands.md](docs/commands.md)

## B. 其他資訊
### B.1. 資料庫設計
1. ERD 圖
   - 參考檔案：`docs/ERD.png`
2. 資料表關聯
   - 參考檔案：`database/migrations/` 目錄下的所有檔案
3. 索引設計
   - 參考檔案：`database/migrations/` 目錄下的所有檔案
