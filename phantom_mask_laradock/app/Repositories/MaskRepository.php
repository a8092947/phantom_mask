<?php

namespace App\Repositories;

use App\Models\Mask;

class MaskRepository extends BaseRepository
{
    /**
     * MaskRepository constructor.
     *
     * @param Mask $model
     */
    public function __construct(Mask $model)
    {
        parent::__construct($model);
    }

    /**
     * 取得藥局的口罩列表
     *
     * @param int $pharmacyId
     * @param float|null $minPrice
     * @param float|null $maxPrice
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPharmacyMasks(int $pharmacyId, ?float $minPrice = null, ?float $maxPrice = null, int $perPage = 15)
    {
        $query = $this->model->where('pharmacy_id', $pharmacyId);

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        return $query->orderBy('price')
            ->paginate($perPage);
    }

    /**
     * 更新口罩庫存
     *
     * @param int $maskId
     * @param int $quantity
     * @return bool
     */
    public function updateStock(int $maskId, int $quantity)
    {
        return $this->model->where('id', $maskId)
            ->update(['stock' => $quantity]);
    }

    /**
     * 取得口罩統計
     *
     * @param float|null $minPrice
     * @param float|null $maxPrice
     * @return array
     */
    public function getMaskStats(?float $minPrice = null, ?float $maxPrice = null)
    {
        $query = $this->model;

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        return [
            'total_masks' => $query->count(),
            'total_stock' => $query->sum('stock'),
            'average_price' => $query->avg('price'),
            'min_price' => $query->min('price'),
            'max_price' => $query->max('price')
        ];
    }
} 