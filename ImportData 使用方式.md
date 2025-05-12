# 資料匯入工具使用說明

## 命令格式
```bash
php artisan import:data {type} {file} [options]
```

## 必要參數

### type
指定要匯入的資料類型，必須是以下其中一個值：
- `pharmacy`: 匯入藥局資料
- `user`: 匯入用戶資料
- `mask`: 匯入口罩資料
- `transaction`: 匯入交易資料

### file
JSON 檔案路徑，必須是有效的 JSON 檔案。

## 選擇性參數
- `--commit`: 實際寫入資料庫（預設只驗證）
- `--force`: 強制覆蓋已存在的資料
- `--skip-relation-check`: 跳過關聯檢查（僅用於 user 和 transaction）

## 使用範例

### 1. 驗證藥局資料
```bash
php artisan import:data pharmacy data/pharmacies.json
```

### 2. 驗證用戶資料
```bash
php artisan import:data user data/users.json
```

### 3. 驗證口罩資料
```bash
php artisan import:data mask data/masks.json
```

### 4. 驗證交易資料
```bash
php artisan import:data transaction data/transactions.json
```

### 5. 實際匯入藥局資料
```bash
php artisan import:data pharmacy data/pharmacies.json --commit
```

### 6. 強制覆蓋已存在的藥局資料
```bash
php artisan import:data pharmacy data/pharmacies.json --commit --force
```

### 7. 匯入用戶資料（忽略關聯檢查）
```bash
php artisan import:data user data/users.json --commit --skip-relation-check
```

### 8. 匯入交易資料（忽略關聯檢查）
```bash
php artisan import:data transaction data/transactions.json --commit --skip-relation-check
```

## 資料格式範例

### 藥局資料 (pharmacies.json)
```json
[
    {
        "name": "藥局名稱",
        "cashBalance": 1000.00,
        "openingHours": "Mon - Fri 08:00 - 17:00 / Sat 09:00 - 12:00",
        "masks": [
            {
                "name": "口罩名稱",
                "price": 10.00
            }
        ]
    }
]
```

### 用戶資料 (users.json)
```json
[
    {
        "name": "用戶名稱",
        "cashBalance": 500.00,
        "purchaseHistories": [
            {
                "pharmacyName": "藥局名稱",
                "maskName": "口罩名稱",
                "transactionAmount": 100.00,
                "transactionDate": "2024-03-20 10:00:00"
            }
        ]
    }
]
```

### 口罩資料 (masks.json)
```json
[
    {
        "pharmacy_id": 1,
        "name": "口罩名稱",
        "price": 10.00,
        "stock": 100
    }
]
```

### 交易資料 (transactions.json)
```json
[
    {
        "user_id": 1,
        "pharmacy_id": 1,
        "mask_id": 1,
        "quantity": 10,
        "transaction_date": "2024-03-20 10:00:00"
    }
]
```

## 注意事項

1. 資料匯入順序建議：
   - 先匯入藥局資料
   - 再匯入口罩資料
   - 再匯入用戶資料
   - 最後匯入交易資料

2. 驗證機制：
   - 預設只驗證資料，需加 `--commit` 才會寫入
   - 驗證失敗會顯示詳細錯誤訊息
   - 關聯檢查會驗證資料完整性

3. 資料覆蓋：
   - 使用 `--force` 會刪除已存在的關聯資料
   - 藥局資料會刪除營業時間和口罩
   - 用戶資料會刪除交易記錄

4. 關聯檢查：
   - 用戶資料會檢查藥局和口罩是否存在
   - 交易資料會檢查用戶、藥局和口罩是否存在
   - 使用 `--skip-relation-check` 可跳過檢查

5. 錯誤處理：
   - 任何錯誤都會導致整個匯入回滾
   - 錯誤訊息會記錄在 `storage/logs/laravel.log`
   - 建議先修正所有錯誤再執行匯入

6. 營業時間格式：
   - 支援連續日期：`Mon - Fri 08:00 - 17:00`
   - 支援不連續日期：`Mon, Wed, Fri 08:00 - 12:00`
   - 支援跨日營業：`Mon - Fri 22:00 - 06:00`