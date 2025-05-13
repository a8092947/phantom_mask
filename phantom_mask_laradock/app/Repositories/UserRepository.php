<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function getTopSpenders($limit = 10, $startDate = null, $endDate = null)
    {
        $query = $this->model->withSum('transactions', 'amount')
                            ->orderBy('transactions_sum_amount', 'desc');

        if ($startDate || $endDate) {
            $query->whereHas('transactions', function ($q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->where('created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('created_at', '<=', $endDate . ' 23:59:59');
                }
            });
        }

        return $query->take($limit)->get();
    }
} 