# 指令說明

## 1. 資料匯入指令

### 1.1 匯入藥局資料
```bash
php artisan import:data pharmacy ../ata/pharmacies.json --commit
```

參數說明：
- `pharmacy`: 資料類型
- `data/pharmacies.json`: 資料檔案路徑
- `--commit`: 實際寫入資料庫（不加此參數則只驗證）

### 1.2 匯入用戶資料
```bash
php artisan import:data user ../data/users.json --commit
```

參數說明：
- `user`: 資料類型
- `../data/users.json`: 資料檔案路徑
- `--commit`: 實際寫入資料庫（不加此參數則只驗證）

## 2. 資料庫指令

### 2.1 建立資料庫
```bash
php artisan migrate
```

### 2.2 重置資料庫
```bash
php artisan migrate:fresh
```

## 3. 開發指令

### 3.1 啟動開發伺服器
```bash
php artisan serve
```

### 3.2 產生 API 文件
```bash
php artisan l5-swagger:generate
``` 