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
    protected $signature = 'import:data {type} {file} {--commit} {--force}';
    protected $description = '匯入資料到資料庫';

    protected $validTypes = ['pharmacy', 'user', 'mask', 'transaction'];
    protected $weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    public function handle()
    {
        $type = $this->argument('type');
        $file = $this->argument('file');
        $shouldCommit = $this->option('commit');
        $force = $this->option('force');

        if (!in_array($type, $this->validTypes)) {
            $this->error('無效的資料類型');
            return 1;
        }

        if (!file_exists($file)) {
            $this->error('找不到檔案：' . $file);
            return 1;
        }

        $data = json_decode(file_get_contents($file), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('JSON 解析錯誤：' . json_last_error_msg());
            return 1;
        }

        $this->info('開始驗證資料...');
        $validationResults = $this->validateData($type, $data);

        $this->info("\n驗證報告：");
        $this->info("總筆數: " . count($data));
        $this->info("成功: " . $validationResults['success']);
        $this->info("失敗: " . $validationResults['failed']);

        if ($validationResults['failed'] > 0) {
            $this->error("\n驗證失敗！");
            if (!$force) {
                $this->error('請修正錯誤後重試，或使用 --force 參數強制匯入');
                return 1;
            }
            $this->warn('使用 --force 參數，將繼續匯入...');
        } else {
            $this->info("\n驗證通過！");
        }

        if (!$shouldCommit) {
            $this->info("\n使用 --commit 參數來實際寫入資料庫");
            return 0;
        }

        $this->info("\n開始寫入資料庫...");
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        try {
            DB::beginTransaction();

            foreach ($data as $item) {
                $this->importItem($type, $item);
                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->info("\n\n資料匯入完成！");
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n\n資料匯入失敗：" . $e->getMessage());
            Log::error('Import failed', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    protected function validateData($type, $data)
    {
        $success = 0;
        $failed = 0;

        foreach ($data as $item) {
            $validator = $this->getValidator($type, $item);
            if ($validator->passes()) {
                $success++;
            } else {
                $failed++;
                $this->error("\n驗證失敗：");
                foreach ($validator->errors()->all() as $error) {
                    $this->error("- " . $error);
                }
            }
        }

        return [
            'success' => $success,
            'failed' => $failed
        ];
    }

    protected function getValidator($type, $data)
    {
        $rules = [
            'pharmacy' => [
                'name' => 'required|string|max:255',
                'cashBalance' => 'required|numeric|min:0',
                'openingHours' => 'required|string',
                'masks' => 'required|array',
                'masks.*.name' => 'required|string|max:255',
                'masks.*.price' => 'required|numeric|min:0'
            ],
            'user' => [
                'name' => 'required|string|max:255',
                'balance' => 'required|numeric|min:0'
            ],
            'mask' => [
                'pharmacy_id' => 'required|exists:pharmacies,id',
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0'
            ],
            'transaction' => [
                'user_id' => 'required|exists:pharmacy_users,id',
                'pharmacy_id' => 'required|exists:pharmacies,id',
                'mask_id' => 'required|exists:masks,id',
                'quantity' => 'required|integer|min:1',
                'transaction_date' => 'required|date'
            ]
        ];

        return Validator::make($data, $rules[$type]);
    }

    protected function importItem($type, $item)
    {
        switch ($type) {
            case 'pharmacy':
                $this->importPharmacy($item);
                break;
            case 'user':
                $this->importUser($item);
                break;
            case 'mask':
                $this->importMask($item);
                break;
            case 'transaction':
                $this->importTransaction($item);
                break;
        }
    }

    protected function importPharmacy($data)
    {
        $pharmacy = Pharmacy::create([
            'name' => $data['name'],
            'cash_balance' => $data['cashBalance']
        ]);

        // 解析營業時間字串
        $openingHours = $this->parseOpeningHours($data['openingHours']);
        foreach ($openingHours as $hour) {
            OpeningHour::create([
                'pharmacy_id' => $pharmacy->id,
                'day_of_week' => $hour['day_of_week'],
                'open_time' => $hour['open_time'],
                'close_time' => $hour['close_time']
            ]);
        }

        // 匯入口罩資料
        foreach ($data['masks'] as $maskData) {
            Mask::create([
                'pharmacy_id' => $pharmacy->id,
                'name' => $maskData['name'],
                'price' => $maskData['price'],
                'stock' => 100 // 預設庫存
            ]);
        }
    }

    protected function parseOpeningHours($openingHoursStr)
    {
        $result = [];
        $parts = explode('/', $openingHoursStr);

        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/([A-Za-z,\s-]+)\s+(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})/', $part, $matches)) {
                $days = $this->parseDays($matches[1]);
                $openTime = $matches[2];
                $closeTime = $matches[3];

                // 處理跨日的情況
                if (strtotime($closeTime) < strtotime($openTime)) {
                    // 如果結束時間小於開始時間，表示跨日
                    $closeTime = date('H:i:s', strtotime($closeTime . ' +1 day'));
                } else {
                    // 確保時間格式為 HH:MM:SS
                    $openTime = date('H:i:s', strtotime($openTime));
                    $closeTime = date('H:i:s', strtotime($closeTime));
                }

                foreach ($days as $day) {
                    $result[] = [
                        'day_of_week' => $this->convertDayOfWeek($day),
                        'open_time' => $openTime,
                        'close_time' => $closeTime
                    ];
                }
            }
        }

        return $result;
    }

    protected function parseDays($daysStr)
    {
        $days = [];
        $parts = explode(',', $daysStr);

        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, '-') !== false) {
                // 處理範圍，例如 "Mon - Fri"
                list($start, $end) = explode('-', $part);
                $start = trim($start);
                $end = trim($end);
                $startIndex = array_search($start, $this->weekDays);
                $endIndex = array_search($end, $this->weekDays);

                if ($startIndex !== false && $endIndex !== false) {
                    for ($i = $startIndex; $i <= $endIndex; $i++) {
                        $days[] = $this->weekDays[$i];
                    }
                }
            } else {
                // 單一日期
                $day = trim($part);
                if (in_array($day, $this->weekDays)) {
                    $days[] = $day;
                }
            }
        }

        return $days;
    }

    protected function importUser($data)
    {
        PharmacyUser::create([
            'name' => $data['name'],
            'balance' => $data['balance']
        ]);
    }

    protected function importMask($data)
    {
        Mask::create([
            'pharmacy_id' => $data['pharmacy_id'],
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock']
        ]);
    }

    protected function importTransaction($data)
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

    protected function convertDayOfWeek($day)
    {
        $index = array_search($day, $this->weekDays);
        if ($index === false) {
            throw new \Exception("無效的星期幾：{$day}");
        }
        return $index; // 直接返回 0-6 的索引
    }

    protected function calculateTransactionAmount($data)
    {
        $mask = Mask::findOrFail($data['mask_id']);
        return $mask->price * $data['quantity'];
    }
} 