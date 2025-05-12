# 系統設計文件

## 1. 系統架構變更記錄

### 1.1 資料庫結構調整
- 移除 Laravel 預設的 `users` 表，避免與藥局系統的用戶表衝突
- 建立新的 `pharmacy_users` 表來管理藥局系統的用戶
- 調整所有相關的外鍵引用，確保資料完整性

### 1.2 遷移檔案順序
1. `2024_05_11_000001_create_pharmacies_table.php`
2. `2024_05_11_000002_create_opening_hours_table.php`
3. `2024_05_11_000003_create_masks_table.php`
4. `2024_05_11_000004_create_pharmacy_users_table.php`
5. `2024_05_11_000005_create_transactions_table.php`

## 2. 系統需求分析

### 2.1 原始資料結構
系統需要處理兩個主要的 JSON 資料檔案：

#### 2.1.1 藥局資料 (pharmacies.json)
```json
{
  "name": "DFW Wellness",
  "cashBalance": 328.41,
  "openingHours": "Mon, Wed, Fri 08:00 - 12:00 / Tue, Thur 14:00 - 18:00",
  "masks": [
    {
      "name": "True Barrier (green) (3 per pack)",
      "price": 13.7
    }
  ]
}
```

#### 2.1.2 用戶資料 (users.json)
```json
{
  "name": "Yvonne Guerrero",
  "cashBalance": 191.83,
  "purchaseHistories": [
    {
      "pharmacyName": "Keystone Pharmacy",
      "maskName": "True Barrier (green) (3 per pack)",
      "transactionAmount": 12.35,
      "transactionDate": "2021-01-04 15:18:51"
    }
  ]
}
```

### 2.2 系統功能需求
1. 查詢特定時間營業的藥局
2. 查詢藥局販售的口罩產品
3. 查詢特定價格範圍的藥局
4. 查詢用戶交易排名
5. 查詢交易統計
6. 搜尋藥局和口罩
7. 處理口罩購買交易

## 3. 資料庫設計

### 3.1 資料表結構

#### 3.1.1 藥局表 (pharmacies)
| 欄位名稱 | 型態 | 說明 |
|---------|------|------|
| id | BIGINT | 藥局唯一識別碼 |
| name | VARCHAR(255) | 藥局名稱 |
| cash_balance | DECIMAL(10,2) | 藥局現金餘額 |
| created_at | TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | 更新時間 |

#### 3.1.2 營業時間表 (opening_hours)
| 欄位名稱 | 型態 | 說明 |
|---------|------|------|
| id | BIGINT | 營業時間唯一識別碼 |
| pharmacy_id | BIGINT | 關聯的藥局ID |
| day_of_week | VARCHAR(10) | 星期幾 |
| open_time | TIME | 開始營業時間 |
| close_time | TIME | 結束營業時間 |
| created_at | TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | 更新時間 |

#### 3.1.3 口罩產品表 (masks)
| 欄位名稱 | 型態 | 說明 |
|---------|------|------|
| id | BIGINT | 口罩產品唯一識別碼 |
| pharmacy_id | BIGINT | 關聯的藥局ID |
| name | VARCHAR(255) | 口罩產品名稱 |
| price | DECIMAL(10,2) | 口罩產品價格 |
| created_at | TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | 更新時間 |

#### 3.1.4 用戶表 (pharmacy_users)
| 欄位名稱 | 型態 | 說明 |
|---------|------|------|
| id | BIGINT | 用戶唯一識別碼 |
| name | VARCHAR(255) | 用戶姓名 |
| cash_balance | DECIMAL(10,2) | 用戶現金餘額 |
| created_at | TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | 更新時間 |

#### 3.1.5 交易記錄表 (transactions)
| 欄位名稱 | 型態 | 說明 |
|---------|------|------|
| id | BIGINT | 交易記錄唯一識別碼 |
| user_id | BIGINT | 關聯的用戶ID |
| pharmacy_id | BIGINT | 關聯的藥局ID |
| mask_id | BIGINT | 關聯的口罩產品ID |
| transaction_amount | DECIMAL(10,2) | 交易金額 |
| transaction_date | TIMESTAMP | 交易時間 |
| created_at | TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | 更新時間 |

### 3.2 設計理念

#### 3.2.1 資料正規化
1. 將複雜的 JSON 結構拆分成多個關聯表
2. 避免資料重複
3. 確保資料一致性

#### 3.2.2 查詢效率
1. 使用適當的索引（主鍵和外鍵）
2. 將常用查詢的欄位獨立出來
3. 優化查詢效能

#### 3.2.3 資料完整性
1. 使用外鍵約束確保關聯資料的完整性
2. 使用 cascade 刪除確保資料一致性
3. 使用適當的資料型態確保資料正確性

#### 3.2.4 資料型態選擇
1. 使用 decimal 型態儲存金額，確保精確度
2. 使用適當的字串長度限制
3. 使用 timestamp 型態儲存時間

#### 3.2.5 可維護性
1. 加入詳細的註解說明
2. 使用有意義的欄位名稱
3. 遵循 Laravel 的命名慣例

### 3.3 關聯關係
1. 藥局 1:N 營業時間
2. 藥局 1:N 口罩產品
3. 用戶 1:N 交易記錄
4. 藥局 1:N 交易記錄
5. 口罩產品 1:N 交易記錄

### 3.4 索引設計
1. 所有表都使用 id 作為主鍵
2. 所有外鍵欄位都建立索引
3. 常用查詢欄位建立索引：
   - pharmacies.name
   - masks.name
   - transactions.transaction_date
   - opening_hours.day_of_week

### 3.5 資料完整性約束
1. 所有外鍵都設定為 CASCADE 刪除
2. 金額欄位都設定為 NOT NULL 和 DEFAULT 0
3. 時間欄位都設定為 NOT NULL
4. 名稱欄位都設定為 NOT NULL

## 4. 系統變更記錄

### 4.1 2024-05-11
1. 移除 Laravel 預設的 `users` 表
2. 建立新的 `pharmacy_users` 表
3. 調整所有相關的外鍵引用
4. 建立完整的資料庫結構
5. 加入詳細的欄位註解
6. 確保資料庫遷移順序正確

### 4.2 待辦事項
1. 實作 Model 層
2. 實作資料匯入功能
3. 實作 API 控制器
4. 實作 API 路由
5. 撰寫 API 文件

## 5. Model 層設計

### 5.1 Model 關聯關係
1. Pharmacy (藥局)
   - hasMany OpeningHour (營業時間)
   - hasMany Mask (口罩產品)
   - hasMany Transaction (交易記錄)

2. OpeningHour (營業時間)
   - belongsTo Pharmacy (藥局)

3. Mask (口罩產品)
   - belongsTo Pharmacy (藥局)
   - hasMany Transaction (交易記錄)

4. PharmacyUser (用戶)
   - hasMany Transaction (交易記錄)

5. Transaction (交易記錄)
   - belongsTo PharmacyUser (用戶)
   - belongsTo Pharmacy (藥局)
   - belongsTo Mask (口罩產品)

### 5.2 Model 屬性設定
1. 可批量賦值屬性 (fillable)
   - 設定每個 Model 可被批量賦值的欄位
   - 確保資料安全性

2. 日期轉換 (casts)
   - 金額欄位使用 decimal:2
   - 時間欄位使用 datetime
   - 確保資料型態正確

### 5.3 設計理念
1. 關聯關係
   - 使用適當的關聯方法
   - 確保資料完整性
   - 方便資料查詢

2. 資料型態
   - 使用適當的型態轉換
   - 確保資料精確度
   - 方便資料處理

3. 安全性
   - 使用 fillable 控制可賦值欄位
   - 避免大量賦值漏洞
   - 確保資料安全

### 5.4 系統變更記錄

#### 5.4.1 2024-05-11
1. 建立所有 Model 檔案
2. 設定 Model 關聯關係
3. 設定 Model 屬性
4. 加入詳細的註解說明

### 6.4 系統變更記錄

#### 6.4.1 2024-05-11
1. 建立資料匯入命令
2. 實作藥局資料匯入功能
3. 實作用戶資料匯入功能
4. 加入錯誤處理機制
5. 加入詳細的註解說明

#### 6.4.2 2024-05-11
1. 修正 Transaction 資料表欄位名稱
   - 將 `pharmacy_user_id` 改為 `user_id`
   - 確保與資料表結構一致
   - 修正資料匯入時的欄位對應

2. 改進錯誤處理機制
   - 加入更詳細的錯誤訊息
   - 優化交易回滾機制
   - 提供更清晰的錯誤報告

3. 優化資料驗證
   - 加入欄位名稱檢查
   - 改進關聯資料驗證
   - 提供更完整的驗證報告

4. 更新使用文件
   - 修正命令參數說明
   - 加入欄位名稱說明
   - 提供更詳細的使用範例

### 6.5 待辦事項
1. 加入資料驗證規則
2. 實作資料匯出功能
3. 加入進度顯示
4. 加入資料備份機制
5. 優化錯誤處理機制
6. 改進資料驗證規則
7. 更新使用文件


## 7. API 實作

### 7.1 API 路由設計
1. 藥局相關
   - GET /api/pharmacies - 列出所有藥局
   - GET /api/pharmacies/{id} - 顯示指定藥局詳細資訊

2. 口罩相關
   - GET /api/masks - 列出所有口罩
   - GET /api/masks/{id} - 顯示指定口罩詳細資訊

3. 用戶相關
   - GET /api/users - 列出所有用戶
   - GET /api/users/{id} - 顯示指定用戶詳細資訊
   - GET /api/users/top - 列出交易金額最高的用戶

4. 交易相關
   - GET /api/transactions - 列出所有交易
   - GET /api/transactions/stats - 顯示交易統計
   - POST /api/transactions - 建立新交易

### 7.2 系統變更記錄

#### 7.2.1 2024-05-12
1. 建立 API 控制器
   - PharmacyController
   - MaskController
   - UserController
   - TransactionController

2. 實作基本 API 路由
   - 設定 RESTful 路由
   - 加入路由群組
   - 實作基本查詢功能

3. 實作藥局查詢功能
   - 支援時間和星期幾篩選
   - 支援價格範圍篩選
   - 支援口罩數量篩選
   - 支援名稱搜尋


#### 7.2.2 2024-05-12
1. 實作口罩查詢功能
   - 支援按藥局篩選
   - 支援價格範圍篩選
   - 支援名稱搜尋
   - 支援按名稱或價格排序
   - 實作相關性排序搜尋

2. 功能說明
   - 列表查詢支援多種篩選條件
   - 搜尋功能支援相關性排序
   - 詳細資訊查詢包含關聯資料

3. 使用範例
   ```bash
   # 列出所有口罩
   GET /api/masks

   # 列出指定藥局的口罩，按價格排序
   GET /api/masks?pharmacy_id=1&sort_by=price&sort_order=desc

   # 搜尋口罩
   GET /api/masks/search?q=green
   ```

4. 注意事項
   - 搜尋結果按相關性排序
   - 支援分頁功能
   - 包含關聯資料


#### 7.2.3 2024-05-12
1. 實作用戶查詢功能
   - 支援用戶列表查詢
   - 支援用戶詳細資訊查詢
   - 支援用戶搜尋
   - 實作交易金額排名功能

2. 功能說明
   - 列表查詢支援多種篩選條件
   - 詳細資訊包含交易統計
   - 排名功能支援日期範圍篩選
   - 搜尋功能支援相關性排序

3. 使用範例
   ```bash
   # 列出所有用戶
   GET /api/users

   # 查看用戶詳細資訊
   GET /api/users/1

   # 查看交易金額排名
   GET /api/users/top?limit=5&start_date=2024-01-01&end_date=2024-12-31

   # 搜尋用戶
   GET /api/users/search?q=john
   ```

4. 注意事項
   - 排名功能預設顯示前 10 名
   - 支援按日期範圍篩選排名
   - 詳細資訊包含交易統計資料
   - 搜尋結果按相關性排序


#### 7.2.4 2024-05-12
1. 實作交易相關功能
   - 支援交易列表查詢
   - 支援交易詳細資訊查詢
   - 實作交易統計功能
   - 實作新增交易功能

2. 功能說明
   - 列表查詢支援多種篩選條件
   - 統計功能包含總金額、次數、平均值
   - 支援每日交易統計
   - 支援藥局交易統計
   - 新增交易時進行多重驗證

3. 使用範例
   ```bash
   # 列出所有交易
   GET /api/transactions

   # 查看交易詳細資訊
   GET /api/transactions/1

   # 查看交易統計
   GET /api/transactions/stats?start_date=2024-01-01&end_date=2024-12-31

   # 新增交易
   POST /api/transactions
   {
       "user_id": 1,
       "pharmacy_id": 1,
       "mask_id": 1,
       "amount": 100
   }
   ```

4. 注意事項
   - 新增交易時會驗證口罩歸屬
   - 新增交易時會驗證價格
   - 統計功能支援日期範圍篩選
   - 支援按多種條件排序