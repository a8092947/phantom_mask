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