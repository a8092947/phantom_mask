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

### file
JSON 檔案路徑，必須是有效的 JSON 檔案。

## 選擇性參數
- `--commit`: 實際寫入資料庫（預設只驗證）
- `--force`: 強制覆蓋已存在的資料
- `--skip-relation-check`: 跳過關聯檢查（僅用於 user）

## 使用範例

### 1. 驗證藥局資料
```bash
php artisan import:data pharmacy data/pharmacies.json
```

### 2. 驗證用戶資料
```bash
php artisan import:data user data/users.json
```

### 3. 實際匯入藥局資料
```bash
php artisan import:data pharmacy data/pharmacies.json --commit
```

### 4. 強制覆蓋已存在的藥局資料
```bash
php artisan import:data pharmacy data/pharmacies.json --commit --force
```

### 5. 匯入用戶資料（忽略關聯檢查）
```bash
php artisan import:data user data/users.json --commit --skip-relation-check
```

## 注意事項

- 建議先匯入藥局資料，再匯入用戶資料，確保關聯完整。
- 預設只驗證資料，需加 `--commit` 才會寫入。
- 匯入用戶資料時會檢查藥局和口罩是否存在，若要跳過請加 `--skip-relation-check`。
- 任何錯誤都會導致整個匯入回滾，請先修正所有錯誤再執行匯入。