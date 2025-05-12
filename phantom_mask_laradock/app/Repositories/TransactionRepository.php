<?php

namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionRepository extends BaseRepository
{
    /**
     * TransactionRepository constructor.
     *
     * @param Transaction $model
     */
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }

    /**
     * 取得使用者的交易列表
     *
     * @param int $userId
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string $sortOrder
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUserTransactions(int $userId, ?string $startDate = null, ?string $endDate = null, string $sortOrder = 'desc', int $perPage = 15)
    {
        $query = $this->model->where('user_id', $userId);

        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('transaction_date', '<=', $endDate);
        }

        return $query->orderBy('amount', $sortOrder)
            ->with(['user', 'pharmacy', 'mask'])
            ->paginate($perPage);
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
        $stats = $this->model->whereBetween('transaction_date', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(quantity) as total_masks'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->first();

        return [
            'total_transactions' => $stats->total_transactions ?? 0,
            'total_masks' => $stats->total_masks ?? 0,
            'total_amount' => $stats->total_amount ?? 0
        ];
    }
} 