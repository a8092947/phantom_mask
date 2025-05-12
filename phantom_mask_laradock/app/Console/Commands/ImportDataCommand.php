<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pharmacy;
use App\Models\OpeningHour;
use App\Models\PharmacyUser;
use App\Models\Mask;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ImportDataCommand extends Command
{
    protected $signature = 'import:data 
        {type : 要匯入的資料類型 (pharmacy/user/mask/transaction)} 
        {file : JSON 檔案路徑}
        {--commit : 實際寫入資料庫（預設只驗證）}
        {--force : 強制覆蓋已存在的資料}
        {--skip-relation-check : 跳過關聯檢查}';

    protected $description = '匯入資料到資料庫（預設只驗證，需加 --commit 才會寫入）';

    protected $validTypes = ['pharmacy', 'user', 'mask', 'transaction'];
    protected $weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    protected $stats = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => [],
        'warnings' => []
    ];

    public function handle()
    {
        $type = $this->argument('type');
        $file = $this->argument('file');
        $shouldCommit = $this->option('commit');
        $force = $this->option('force');
        $skipRelationCheck = $this->option('skip-relation-check');

        try {
            // 檢查資料類型
            if (!in_array($type, $this->validTypes)) {
                throw new \Exception('無效的資料類型');
            }

            // 檢查檔案
            if (!file_exists($file)) {
                throw new \Exception("檔案不存在: {$file}");
            }

            // 讀取並解析 JSON
            $content = file_get_contents($file);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON 格式錯誤: ' . json_last_error_msg());
            }

            $this->stats['total'] = count($data);

            // 驗證資料
            $this->info("開始驗證資料...");
            $this->validateData($data, $type, $force, $skipRelationCheck);

            // 顯示驗證結果
            $this->showValidationReport();

            // 如果有錯誤，直接結束
            if (!empty($this->stats['errors'])) {
                $this->error("驗證失敗，請修正所有錯誤後再重試。");
                return 1;
            }

            $this->info("驗證通過！");

            // 如果沒有 --commit，就結束
            if (!$shouldCommit) {
                $this->info("未加 --commit，未寫入資料庫。");
                $this->info("請確認無誤後加上 --commit 參數進行匯入。");
                return 0;
            }

            // 開始匯入
            $this->info("開始寫入資料庫...");
            DB::beginTransaction();

            try {
                $bar = $this->output->createProgressBar(count($data));
                $bar->start();

                foreach ($data as $item) {
                    $this->importItem($type, $item, $force);
                    $bar->advance();
                }

                DB::commit();
                $bar->finish();
                $this->info("\n\n資料匯入完成！");
                return 0;
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->error("錯誤: " . $e->getMessage());
            Log::error("匯入失敗", [
                'type' => $type,
                'file' => $file,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    protected function validateData(array $data, string $type, bool $force, bool $skipRelationCheck)
    {
        foreach ($data as $index => $item) {
            try {
                switch ($type) {
                    case 'pharmacy':
                        $this->validatePharmacy($item, $index);
                        break;
                    case 'user':
                        $this->validateUser($item, $index, $skipRelationCheck);
                        break;
                    case 'mask':
                        $this->validateMask($item, $index);
                        break;
                    case 'transaction':
                        $this->validateTransaction($item, $index);
                        break;
                }
                $this->stats['success']++;
            } catch (\Exception $e) {
                $this->stats['failed']++;
                $this->stats['errors'][] = [
                    'index' => $index,
                    'data' => $item,
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    protected function validatePharmacy(array $data, int $index)
    {
        // 基本欄位驗證
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'cashBalance' => 'required|numeric|min:0',
            'openingHours' => 'required|string',
            'masks' => 'required|array'
        ]);

        if ($validator->fails()) {
            throw new \Exception("藥局資料驗證失敗: " . json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
        }

        // 驗證營業時間格式
        if (!$this->validateOpeningHours($data['openingHours'])) {
            throw new \Exception("無效的營業時間格式: {$data['openingHours']}");
        }

        // 驗證口罩資料
        $maskNames = [];
        foreach ($data['masks'] as $mask) {
            $maskValidator = Validator::make($mask, [
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0'
            ]);

            if ($maskValidator->fails()) {
                throw new \Exception("口罩資料驗證失敗: " . json_encode($maskValidator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
            }

            if (in_array($mask['name'], $maskNames)) {
                throw new \Exception("同一藥局內口罩名稱重複: {$mask['name']}");
            }
            $maskNames[] = $mask['name'];
        }
    }

    protected function validateUser(array $data, int $index, bool $skipRelationCheck)
    {
        // 基本欄位驗證
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'cashBalance' => 'required|numeric|min:0',
            'purchaseHistories' => 'required|array'
        ]);

        if ($validator->fails()) {
            throw new \Exception("用戶資料驗證失敗: " . json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
        }

        // 驗證購買記錄
        foreach ($data['purchaseHistories'] as $historyIndex => $history) {
            $historyValidator = Validator::make($history, [
                'pharmacyName' => 'required|string|max:255',
                'maskName' => 'required|string|max:255',
                'transactionAmount' => 'required|numeric|min:0',
                'transactionDate' => 'required|date'
            ]);

            if ($historyValidator->fails()) {
                throw new \Exception("購買記錄 #{$historyIndex} 驗證失敗: " . 
                    json_encode($historyValidator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
            }

            // 關聯檢查（如果需要）
            if (!$skipRelationCheck) {
                $pharmacy = Pharmacy::where('name', $history['pharmacyName'])->first();
                if (!$pharmacy) {
                    $this->stats['warnings'][] = [
                        'index' => $index,
                        'history_index' => $historyIndex,
                        'message' => "找不到藥局: {$history['pharmacyName']}"
                    ];
                } else {
                    $mask = Mask::where('pharmacy_id', $pharmacy->id)
                        ->where('name', $history['maskName'])
                        ->first();
                    if (!$mask) {
                        $this->stats['warnings'][] = [
                            'index' => $index,
                            'history_index' => $historyIndex,
                            'message' => "找不到口罩: {$history['maskName']} (藥局: {$history['pharmacyName']})"
                        ];
                    }
                }
            }
        }
    }

    protected function validateMask(array $data, int $index)
    {
        $validator = Validator::make($data, [
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            throw new \Exception("口罩資料驗證失敗: " . json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
        }
    }

    protected function validateTransaction(array $data, int $index)
    {
        $validator = Validator::make($data, [
            'user_id' => 'required|exists:pharmacy_users,id',
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'mask_id' => 'required|exists:masks,id',
            'quantity' => 'required|integer|min:1',
            'transaction_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            throw new \Exception("交易資料驗證失敗: " . json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
        }
    }

    protected function validateOpeningHours(string $openingHours): bool
    {
        $periods = explode(' / ', $openingHours);
        foreach ($periods as $period) {
            // 檢查連續日期格式 (Mon - Fri 08:00 - 17:00)
            if (preg_match('/^([A-Za-z]+)\s*-\s*([A-Za-z]+)\s+(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $period)) {
                continue;
            }
            // 檢查不連續日期格式 (Mon, Wed, Fri 08:00 - 12:00)
            if (preg_match('/^([A-Za-z]+(?:,\s*[A-Za-z]+)*)\s+(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $period)) {
                continue;
            }
            return false;
        }
        return true;
    }

    protected function importItem(string $type, array $item, bool $force)
    {
        switch ($type) {
            case 'pharmacy':
                $this->importPharmacy($item, $force);
                break;
            case 'user':
                $this->importUser($item, $force);
                break;
            case 'mask':
                $this->importMask($item);
                break;
            case 'transaction':
                $this->importTransaction($item);
                break;
        }
    }

    protected function importPharmacy(array $data, bool $force)
    {
        $pharmacy = Pharmacy::updateOrCreate(
            ['name' => $data['name']],
            ['cash_balance' => $data['cashBalance']]
        );

        if ($force) {
            $pharmacy->openingHours()->delete();
            $pharmacy->masks()->delete();
        }

        $this->processOpeningHours($pharmacy, $data['openingHours']);

        foreach ($data['masks'] as $maskData) {
            Mask::create([
                'pharmacy_id' => $pharmacy->id,
                'name' => $maskData['name'],
                'price' => $maskData['price'],
                'stock' => 100 // 預設庫存
            ]);
        }
    }

    protected function importUser(array $data, bool $force)
    {
        $user = PharmacyUser::updateOrCreate(
            ['name' => $data['name']],
            ['cash_balance' => $data['cashBalance']]
        );

        if ($force) {
            $user->transactions()->delete();
        }

        foreach ($data['purchaseHistories'] as $history) {
            $pharmacy = Pharmacy::where('name', $history['pharmacyName'])->first();
            if (!$pharmacy) {
                continue;
            }

            $mask = Mask::where('pharmacy_id', $pharmacy->id)
                ->where('name', $history['maskName'])
                ->first();
            if (!$mask) {
                continue;
            }

            Transaction::create([
                'user_id' => $user->id,
                'pharmacy_id' => $pharmacy->id,
                'mask_id' => $mask->id,
                'quantity' => 1, // 預設數量
                'amount' => $history['transactionAmount'],
                'transaction_date' => Carbon::parse($history['transactionDate'])
            ]);
        }
    }

    protected function importMask(array $data)
    {
        Mask::create([
            'pharmacy_id' => $data['pharmacy_id'],
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock']
        ]);
    }

    protected function importTransaction(array $data)
    {
        Transaction::create([
            'user_id' => $data['user_id'],
            'pharmacy_id' => $data['pharmacy_id'],
            'mask_id' => $data['mask_id'],
            'quantity' => $data['quantity'],
            'amount' => $this->calculateTransactionAmount($data),
            'transaction_date' => $data['transaction_date']
        ]);
    }

    private function processOpeningHours(Pharmacy $pharmacy, string $openingHours): void
    {
        // 先刪除現有的營業時間
        $pharmacy->openingHours()->delete();

        // 解析營業時間字串
        $parts = explode('/', $openingHours);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // 解析日期和時間
            if (preg_match('/^([^0-9]+)\s+(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $part, $matches)) {
                $days = trim($matches[1]);
                $openTime = $matches[2];
                $closeTime = $matches[3];

                // 處理日期範圍
                if (strpos($days, '-') !== false) {
                    // 處理連續日期範圍 (例如: Mon - Fri)
                    list($startDay, $endDay) = array_map('trim', explode('-', $days));
                    $startDayIndex = $this->getDayIndex($startDay);
                    $endDayIndex = $this->getDayIndex($endDay);
                    
                    if ($startDayIndex !== null && $endDayIndex !== null) {
                        for ($i = $startDayIndex; $i <= $endDayIndex; $i++) {
                            $this->createOpeningHour($pharmacy, $i, $openTime, $closeTime);
                        }
                    }
                } else {
                    // 處理不連續日期 (例如: Mon, Wed, Fri)
                    $days = array_map('trim', explode(',', $days));
                    foreach ($days as $day) {
                        $dayIndex = $this->getDayIndex($day);
                        if ($dayIndex !== null) {
                            $this->createOpeningHour($pharmacy, $dayIndex, $openTime, $closeTime);
                        }
                    }
                }
            }
        }
    }

    private function createOpeningHour(Pharmacy $pharmacy, int $dayIndex, string $openTime, string $closeTime): void
    {
        // 檢查是否已存在相同的營業時間記錄
        $exists = $pharmacy->openingHours()
            ->where('day_of_week', $dayIndex)
            ->where('open_time', $openTime)
            ->where('close_time', $closeTime)
            ->exists();

        if (!$exists) {
            $pharmacy->openingHours()->create([
                'day_of_week' => $dayIndex,
                'open_time' => $openTime,
                'close_time' => $closeTime
            ]);
        }
    }

    protected function calculateTransactionAmount(array $data): float
    {
        $mask = Mask::findOrFail($data['mask_id']);
        return $mask->price * $data['quantity'];
    }

    protected function showValidationReport()
    {
        $this->info("\n驗證報告：");
        $this->info("總筆數: {$this->stats['total']}");
        $this->info("成功: {$this->stats['success']}");
        $this->info("失敗: {$this->stats['failed']}");

        if (!empty($this->stats['errors'])) {
            $this->error("\n錯誤：");
            foreach ($this->stats['errors'] as $error) {
                $this->error("第 {$error['index']} 筆: {$error['error']}");
            }
        }

        if (!empty($this->stats['warnings'])) {
            $this->warn("\n警告：");
            foreach ($this->stats['warnings'] as $warning) {
                $this->warn("第 {$warning['index']} 筆: {$warning['message']}");
            }
        }
    }

    private function getDayIndex(string $day): ?int
    {
        $dayMap = [
            'Mon' => 0,
            'Tue' => 1,
            'Wed' => 2,
            'Thu' => 3,
            'Fri' => 4,
            'Sat' => 5,
            'Sun' => 6
        ];

        $day = trim($day);
        return $dayMap[$day] ?? null;
    }
} 