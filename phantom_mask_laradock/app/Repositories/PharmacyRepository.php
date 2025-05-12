<?php

namespace App\Repositories;

use App\Models\Pharmacy;

class PharmacyRepository extends BaseRepository
{
    /**
     * PharmacyRepository constructor.
     *
     * @param Pharmacy $model
     */
    public function __construct(Pharmacy $model)
    {
        parent::__construct($model);
    }

    /**
     * 取得營業中的藥局列表
     *
     * @param string|null $time
     * @param int|null $dayOfWeek
     * @param float|null $minPrice
     * @param float|null $maxPrice
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getOpenPharmacies(?string $time = null, ?int $dayOfWeek = null, ?float $minPrice = null, ?float $maxPrice = null, int $perPage = 15)
    {
        $query = $this->model->with(['openingHours', 'masks']);

        if ($time && $dayOfWeek) {
            $query->whereHas('openingHours', function ($query) use ($time, $dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->where('open_time', '<=', $time)
                    ->where('close_time', '>=', $time);
            });
        }

        if ($minPrice !== null || $maxPrice !== null) {
            $query->whereHas('masks', function ($query) use ($minPrice, $maxPrice) {
                if ($minPrice !== null) {
                    $query->where('price', '>=', $minPrice);
                }
                if ($maxPrice !== null) {
                    $query->where('price', '<=', $maxPrice);
                }
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * 搜尋藥局
     *
     * @param string $query
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchPharmacies(string $query)
    {
        return $this->model->where('name', 'like', "%{$query}%")
            ->with(['openingHours', 'masks'])
            ->get();
    }

    /**
     * 取得藥局口罩統計
     *
     * @param float|null $minPrice
     * @param float|null $maxPrice
     * @return array
     */
    public function getPharmacyMaskStats(?float $minPrice = null, ?float $maxPrice = null)
    {
        $query = $this->model->withCount(['masks' => function ($query) use ($minPrice, $maxPrice) {
            if ($minPrice !== null) {
                $query->where('price', '>=', $minPrice);
            }
            if ($maxPrice !== null) {
                $query->where('price', '<=', $maxPrice);
            }
        }]);

        return [
            'total_pharmacies' => $query->count(),
            'pharmacies_with_masks' => $query->having('masks_count', '>', 0)->count(),
            'average_masks_per_pharmacy' => $query->avg('masks_count')
        ];
    }
} 