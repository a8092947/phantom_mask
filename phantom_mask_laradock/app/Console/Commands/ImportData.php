<?php

namespace App\Console\Commands;

use App\Models\Pharmacy;
use App\Models\PharmacyUser;
use App\Models\OpeningHour;
use App\Models\Mask;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImportData extends Command
{
    protected $signature = 'import:data 
        {type : 要匯入的資料類型 (pharmacy/user)} 
        {file : JSON 檔案路徑}
        {--commit : 實際寫入資料庫（預設只驗證）}
        {--force : 強制覆蓋已存在的資料}
        {--skip-relation-check : 跳過關聯檢查（僅用於 user）}';

    protected $description = '匯入藥局或用戶資料（預設只驗證，需加 --commit 才會寫入）';

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
        $commit = $this->option('commit');
        $force = $this->option('force');
        $skipRelationCheck = $this->option('skip-relation-check');

        try {
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
            if (!$commit) {
                $this->info("未加 --commit，未寫入資料庫。");
                $this->info("請確認無誤後加上 --commit 參數進行匯入。");
                return 0;
            }

            // 開始匯入
            $this->info("開始寫入資料庫...");
            DB::beginTransaction();

            try {
                if ($type === 'pharmacy') {
                    $this->importPharmacies($data, $force);
                } else {
                    $this->importUsers($data, $force);
                }

                DB::commit();
                $this->info("匯入成功！");
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
                if ($type === 'pharmacy') {
                    $this->validatePharmacy($item, $index);
                } else {
                    $this->validateUser($item, $index, $skipRelationCheck);
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

    protected function importPharmacies(array $data, bool $force)
    {
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $pharmacyData) {
            $pharmacy = Pharmacy::updateOrCreate(
                ['name' => $pharmacyData['name']],
                ['cash_balance' => $pharmacyData['cashBalance']]
            );

            if ($force) {
                $pharmacy->openingHours()->delete();
                $pharmacy->masks()->delete();
            }

            $this->processOpeningHours($pharmacy, $pharmacyData['openingHours']);

            foreach ($pharmacyData['masks'] as $maskData) {
                Mask::create([
                    'pharmacy_id' => $pharmacy->id,
                    'name' => $maskData['name'],
                    'price' => $maskData['price'],
                    'quantity' => 0  // 因為原始資料沒有這個欄位，預設為 0
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function importUsers(array $data, bool $force)
    {
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();
    
        foreach ($data as $userData) {
            $user = PharmacyUser::updateOrCreate(
                ['name' => $userData['name']],
                ['cash_balance' => $userData['cashBalance']]
            );
    
            if ($force) {
                $user->transactions()->delete();
            }
    
            foreach ($userData['purchaseHistories'] as $history) {
                $pharmacy = Pharmacy::where('name', $history['pharmacyName'])->first();
                if (!$pharmacy) {
                    continue;  // 跳過找不到的藥局
                }
    
                $mask = Mask::where('pharmacy_id', $pharmacy->id)
                    ->where('name', $history['maskName'])
                    ->first();
                if (!$mask) {
                    continue;  // 跳過找不到的口罩
                }
    
                Transaction::create([
                    'user_id' => $user->id, // 這裡修正
                    'pharmacy_id' => $pharmacy->id,
                    'mask_id' => $mask->id,
                    'transaction_amount' => $history['transactionAmount'],
                    'transaction_date' => Carbon::parse($history['transactionDate'])
                ]);
            }
    
            $bar->advance();
        }
    
        $bar->finish();
        $this->newLine();
    }

    protected function processOpeningHours(Pharmacy $pharmacy, string $openingHours)
    {
        $periods = explode(' / ', $openingHours);
        foreach ($periods as $period) {
            // 處理連續日期範圍 (例如: Mon - Fri)
            if (preg_match('/^([A-Za-z]+)\s*-\s*([A-Za-z]+)\s+(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $period, $matches)) {
                $startDay = $matches[1];
                $endDay = $matches[2];
                $openTime = $matches[3];
                $closeTime = $matches[4];

                $days = $this->getDaysInRange($startDay, $endDay);
                foreach ($days as $day) {
                    $this->createOpeningHour($pharmacy, $day, $openTime, $closeTime);
                }
            }
            // 處理不連續日期 (例如: Mon, Wed, Fri)
            else if (preg_match('/^([A-Za-z]+(?:,\s*[A-Za-z]+)*)\s+(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $period, $matches)) {
                $days = array_map('trim', explode(',', $matches[1]));
                $openTime = $matches[2];
                $closeTime = $matches[3];

                foreach ($days as $day) {
                    $this->createOpeningHour($pharmacy, $day, $openTime, $closeTime);
                }
            }
        }
    }

    protected function getDaysInRange(string $startDay, string $endDay): array
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $startIndex = array_search($startDay, $days);
        $endIndex = array_search($endDay, $days);

        if ($startIndex === false || $endIndex === false) {
            throw new \Exception("無效的日期範圍: {$startDay} - {$endDay}");
        }

        // 處理跨週的情況
        if ($endIndex < $startIndex) {
            $endIndex += 7;
        }

        $result = [];
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $result[] = $days[$i % 7];
        }

        return $result;
    }

    protected function createOpeningHour(Pharmacy $pharmacy, string $day, string $openTime, string $closeTime)
    {
        // 處理跨日營業的情況
        $openDateTime = Carbon::createFromFormat('H:i', $openTime);
        $closeDateTime = Carbon::createFromFormat('H:i', $closeTime);

        // 如果結束時間小於開始時間，表示跨日
        if ($closeDateTime->lt($openDateTime)) {
            $closeDateTime->addDay();
        }

        OpeningHour::create([
            'pharmacy_id' => $pharmacy->id,
            'day_of_week' => $day,
            'open_time' => $openTime,
            'close_time' => $closeTime,
        ]);
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
}