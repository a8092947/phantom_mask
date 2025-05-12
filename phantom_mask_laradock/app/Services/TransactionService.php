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

            // 2. 檢查口罩庫存
            $mask = Mask::where('id', $data['mask_id'])
                ->where('pharmacy_id', $data['pharmacy_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($mask->stock <= 0) {
                throw new \Exception('口罩庫存不足');
            }

            // 3. 建立交易
            $transaction = $this->transactionRepository->create([
                'user_id' => $data['user_id'],
                'pharmacy_id' => $data['pharmacy_id'],
                'mask_id' => $data['mask_id'],
                'amount' => $data['amount'],
                'quantity' => 1,
                'transaction_date' => now(),
            ]);

            // 4. 更新使用者餘額
            $this->userRepository->updateBalance($user->id, $user->cash_balance - $data['amount']);

            // 5. 更新藥局餘額
            $pharmacy = Pharmacy::findOrFail($data['pharmacy_id']);
            $pharmacy->cash_balance += $data['amount'];
            $pharmacy->save();

            // 6. 更新口罩庫存
            $this->maskRepository->updateStock($mask->id, $mask->stock - 1);

            // 7. 記錄交易日誌
            Log::info('交易完成', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'pharmacy_id' => $pharmacy->id,
                'mask_id' => $mask->id,
                'amount' => $data['amount']
            ]);

            return $transaction;
        });
    }

    /**
     * 取得交易統計
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getTransactionStats(string $startDate, string $endDate)
    {
        return $this->transactionRepository->getTransactionStats($startDate, $endDate);
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
} 