<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Pharmacy;
use App\Models\Mask;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\MaskRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TransactionService
{
    protected $transactionRepository;
    protected $userRepository;
    protected $maskRepository;

    public function __construct(
        TransactionRepository $transactionRepository,
        UserRepository $userRepository,
        MaskRepository $maskRepository
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->userRepository = $userRepository;
        $this->maskRepository = $maskRepository;
    }

    /**
     * 處理交易
     *
     * @param array $data
     * @return Transaction
     * @throws \Exception
     */
    public function processTransaction(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1. 檢查使用者餘額
            $user = $this->userRepository->findOrFail($data['user_id']);
            if ($user->cash_balance < $data['amount']) {
                throw new \Exception('使用者餘額不足');
            }

            // 2. 建立交易
            $transaction = $this->transactionRepository->create([
                'user_id' => $data['user_id'],
                'pharmacy_id' => $data['pharmacy_id'],
                'mask_id' => $data['mask_id'],
                'amount' => $data['amount'],
                'transaction_date' => now(),
            ]);

            // 3. 更新使用者餘額
            $user->cash_balance -= $data['amount'];
            $user->save();

            // 4. 更新藥局餘額
            $pharmacy = Pharmacy::findOrFail($data['pharmacy_id']);
            $pharmacy->cash_balance += $data['amount'];
            $pharmacy->save();

            // 5. 記錄交易日誌
            Log::info('交易完成', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'pharmacy_id' => $pharmacy->id,
                'mask_id' => $data['mask_id'],
                'amount' => $data['amount']
            ]);

            return $transaction;
        });
    }

    /**
     * 處理口罩購買交易
     *
     * @param array $data
     * @return Transaction
     * @throws \Exception
     */
    public function processMaskPurchase(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1. 檢查口罩庫存
            $mask = $this->maskRepository->findWithLock($data['mask_id']);
            if ($mask->stock < $data['quantity']) {
                throw new \Exception('庫存不足');
            }

            // 2. 檢查使用者餘額
            $user = User::lockForUpdate()->findOrFail($data['user_id']);
            $pharmacy = Pharmacy::lockForUpdate()->findOrFail($data['pharmacy_id']);
            
            $totalAmount = $mask->price * $data['quantity'];
            if ($user->cash_balance < $totalAmount) {
                throw new \Exception('餘額不足');
            }

            // 3. 更新口罩庫存和價格
            $mask->decrement('stock', $data['quantity']);
            $mask->increment('price', 1); // 每次購買後價格增加 1 元

            // 4. 更新用戶餘額
            $user->decrement('cash_balance', $totalAmount);

            // 5. 更新藥局餘額
            $pharmacy->increment('cash_balance', $totalAmount);

            // 6. 建立交易記錄
            $transaction = Transaction::create([
                'user_id' => $data['user_id'],
                'pharmacy_id' => $data['pharmacy_id'],
                'mask_id' => $data['mask_id'],
                'quantity' => $data['quantity'],
                'amount' => $totalAmount
            ]);

            // 7. 記錄交易日誌
            Log::info('口罩購買交易完成', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'pharmacy_id' => $pharmacy->id,
                'mask_id' => $mask->id,
                'quantity' => $data['quantity'],
                'amount' => $totalAmount
            ]);

            return $transaction;
        });
    }

    /**
     * 取得交易統計
     *
     * @param array $params 查詢參數
     * @return array
     */
    public function getTransactionStats(array $params = [])
    {
        try {
            $stats = $this->transactionRepository->getTransactionStats($params);

            // 取得熱門口罩排行
            $topMasks = $this->transactionRepository->getTopMasks($params);

            // 取得熱門藥局排行
            $topPharmacies = $this->transactionRepository->getTopPharmacies($params);

            // 取得交易時段分布
            $timeDistribution = $this->transactionRepository->getTimeDistribution($params);

            return [
                'summary' => $stats,
                'top_masks' => $topMasks,
                'top_pharmacies' => $topPharmacies,
                'time_distribution' => $timeDistribution
            ];
        } catch (\Exception $e) {
            Log::error('查詢交易統計失敗', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('查詢交易統計失敗');
        }
    }

    /**
     * 取得前 N 名消費使用者
     *
     * @param int $limit
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTopSpenders(int $limit, string $startDate, string $endDate)
    {
        return $this->userRepository->getTopSpenders($limit, $startDate, $endDate);
    }

    /**
     * 取得前 N 名消費使用者（帶快取）
     *
     * @param int $limit
     * @param string|null $startDate
     * @param string|null $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTopSpendersWithCache(int $limit, ?string $startDate = null, ?string $endDate = null)
    {
        $cacheKey = "top_spenders:{$limit}:" . md5($startDate . $endDate);
        
        return Cache::remember($cacheKey, 300, function () use ($limit, $startDate, $endDate) {
            return $this->userRepository->getTopSpenders($limit, $startDate, $endDate);
        });
    }

    /**
     * 取得交易統計（帶快取）
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getTransactionStatsWithCache(?string $startDate = null, ?string $endDate = null)
    {
        $cacheKey = "transaction_stats:" . md5($startDate . $endDate);
        
        return Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) {
            return $this->transactionRepository->getTransactionStats($startDate, $endDate);
        });
    }
} 