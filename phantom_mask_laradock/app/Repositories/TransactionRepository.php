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

    public function getTopUsers($limit = 10, $startDate = null, $endDate = null, $sortBy = 'amount', $orderBy = 'desc')
    {
        $query = $this->model->query();

        // 定義可用的統計欄位
        $statFields = [
            'amount' => ['field' => 'amount', 'type' => 'sum'],
            'transaction_count' => ['field' => 'id', 'type' => 'count'],
            'avg_amount' => ['field' => 'amount', 'type' => 'avg'],
            'mask_count' => ['field' => 'quantity', 'type' => 'sum'],
            'last_transaction' => ['field' => 'transaction_date', 'type' => 'max'],
        ];

        // 檢查排序欄位是否有效
        if (!isset($statFields[$sortBy])) {
            throw new \InvalidArgumentException('無效的排序欄位');
        }

        $field = $statFields[$sortBy];
        $statField = "{$field['type']}_{$field['field']}";

        // 根據統計類型構建查詢
        switch ($field['type']) {
            case 'sum':
                $query->select('user_id', DB::raw("SUM({$field['field']}) as {$statField}"));
                break;
            case 'avg':
                $query->select('user_id', DB::raw("AVG({$field['field']}) as {$statField}"));
                break;
            case 'count':
                $query->select('user_id', DB::raw("COUNT({$field['field']}) as {$statField}"));
                break;
            case 'max':
                $query->select('user_id', DB::raw("MAX({$field['field']}) as {$statField}"));
                break;
        }

        // 日期範圍篩選
        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('transaction_date', '<=', $endDate . ' 23:59:59');
        }

        // 分組和排序
        $query->groupBy('user_id')
              ->orderBy($statField, strtolower($orderBy) === 'asc' ? 'asc' : 'desc')
              ->with('user:id,name,cash_balance')
              ->take($limit);

        return $query->get();
    }
} 