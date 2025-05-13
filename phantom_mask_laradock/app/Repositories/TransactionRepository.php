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
     * @param array $params 查詢參數
     * @return array
     */
    public function getTransactionStats(array $params = [])
    {
        $query = Transaction::query();

        // 應用查詢條件
        $this->applyQueryFilters($query, $params);

        $result = $query->select([
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(quantity) as total_masks'),
            DB::raw('SUM(amount) as total_amount'),
            DB::raw('AVG(amount) as average_amount'),
            DB::raw('MAX(amount) as max_amount'),
            DB::raw('MIN(amount) as min_amount'),
            DB::raw('AVG(quantity) as average_quantity')
        ])->first();

        // 如果沒有資料，返回預設值
        if (!$result) {
            return [
                'total_transactions' => 0,
                'total_masks' => 0,
                'total_amount' => 0,
                'average_amount' => 0,
                'max_amount' => 0,
                'min_amount' => 0,
                'average_quantity' => 0
            ];
        }

        return $result->toArray();
    }

    /**
     * 取得熱門口罩排行
     *
     * @param array $params 查詢參數
     * @return array
     */
    public function getTopMasks(array $params = [])
    {
        $query = Transaction::query()
            ->join('masks', 'transactions.mask_id', '=', 'masks.id')
            ->select([
                'masks.id as mask_id',
                'masks.name as mask_name',
                DB::raw('SUM(transactions.quantity) as total_quantity'),
                DB::raw('SUM(transactions.amount) as total_amount')
            ])
            ->groupBy('masks.id', 'masks.name')
            ->orderBy('total_quantity', 'desc')
            ->limit(10);

        // 應用查詢條件
        $this->applyQueryFilters($query, $params);

        return $query->get()->toArray();
    }

    /**
     * 取得熱門藥局排行
     *
     * @param array $params 查詢參數
     * @return array
     */
    public function getTopPharmacies(array $params = [])
    {
        $query = Transaction::query()
            ->join('pharmacies', 'transactions.pharmacy_id', '=', 'pharmacies.id')
            ->select([
                'pharmacies.id as pharmacy_id',
                'pharmacies.name as pharmacy_name',
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(transactions.amount) as total_amount')
            ])
            ->groupBy('pharmacies.id', 'pharmacies.name')
            ->orderBy('total_transactions', 'desc')
            ->limit(10);

        // 應用查詢條件
        $this->applyQueryFilters($query, $params);

        return $query->get()->toArray();
    }

    /**
     * 取得交易時段分布
     *
     * @param array $params 查詢參數
     * @return array
     */
    public function getTimeDistribution(array $params = [])
    {
        $query = Transaction::query()
            ->select([
                DB::raw('COUNT(CASE WHEN HOUR(transaction_date) BETWEEN 6 AND 11 THEN 1 END) as morning'),
                DB::raw('COUNT(CASE WHEN HOUR(transaction_date) BETWEEN 12 AND 17 THEN 1 END) as afternoon'),
                DB::raw('COUNT(CASE WHEN HOUR(transaction_date) BETWEEN 18 AND 23 THEN 1 END) as evening'),
                DB::raw('COUNT(CASE WHEN HOUR(transaction_date) BETWEEN 0 AND 5 THEN 1 END) as night')
            ]);

        // 應用查詢條件
        $this->applyQueryFilters($query, $params);

        $result = $query->first();

        // 如果沒有資料，返回預設值
        if (!$result) {
            return [
                'morning' => 0,
                'afternoon' => 0,
                'evening' => 0,
                'night' => 0
            ];
        }

        return $result->toArray();
    }

    /**
     * 應用查詢條件
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $params
     * @return void
     */
    private function applyQueryFilters($query, array $params)
    {
        // 日期範圍
        if (!empty($params['start_date'])) {
            $query->where('transactions.transaction_date', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('transactions.transaction_date', '<=', $params['end_date']);
        }

        // 藥局
        if (!empty($params['pharmacy_id'])) {
            $query->where('transactions.pharmacy_id', $params['pharmacy_id']);
        }

        // 口罩
        if (!empty($params['mask_id'])) {
            $query->where('transactions.mask_id', $params['mask_id']);
        }

        // 用戶
        if (!empty($params['user_id'])) {
            $query->where('transactions.user_id', $params['user_id']);
        }

        // 分組
        if (!empty($params['group_by'])) {
            switch ($params['group_by']) {
                case 'day':
                    $query->groupBy(DB::raw('DATE(transactions.transaction_date)'));
                    break;
                case 'week':
                    $query->groupBy(DB::raw('YEARWEEK(transactions.transaction_date)'));
                    break;
                case 'month':
                    $query->groupBy(DB::raw('DATE_FORMAT(transactions.transaction_date, "%Y-%m")'));
                    break;
                case 'year':
                    $query->groupBy(DB::raw('YEAR(transactions.transaction_date)'));
                    break;
            }
        }
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