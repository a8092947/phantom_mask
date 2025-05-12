<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository extends BaseRepository
{
    /**
     * UserRepository constructor.
     *
     * @param User $model
     */
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * 取得使用者列表
     *
     * @param string $sortBy
     * @param string $sortOrder
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUsers(string $sortBy = 'balance', string $sortOrder = 'desc', int $perPage = 15)
    {
        return $this->model->orderBy($sortBy, $sortOrder)
            ->with(['transactions.pharmacy', 'transactions.mask'])
            ->paginate($perPage);
    }

    /**
     * 取得消費金額最高的使用者
     *
     * @param int $limit
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTopSpenders(int $limit, string $startDate, string $endDate)
    {
        return $this->model->withSum(['transactions' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }], 'amount')
            ->orderBy('transactions_sum_amount', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 搜尋使用者
     *
     * @param string $query
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchUsers(string $query)
    {
        return $this->model->where('name', 'like', "%{$query}%")
            ->with(['transactions.pharmacy', 'transactions.mask'])
            ->get();
    }

    /**
     * 更新使用者餘額
     *
     * @param int $userId
     * @param float $amount
     * @return bool
     */
    public function updateBalance(int $userId, float $amount)
    {
        return $this->model->where('id', $userId)
            ->update(['balance' => $amount]);
    }
} 