<?php

namespace App\Repositories;

use App\Models\OpeningHour;

class OpeningHourRepository extends BaseRepository
{
    /**
     * OpeningHourRepository constructor.
     *
     * @param OpeningHour $model
     */
    public function __construct(OpeningHour $model)
    {
        parent::__construct($model);
    }

    /**
     * 取得藥局的營業時間
     *
     * @param int $pharmacyId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPharmacyOpeningHours(int $pharmacyId)
    {
        return $this->model->where('pharmacy_id', $pharmacyId)
            ->orderBy('day_of_week')
            ->get();
    }

    /**
     * 檢查營業時間是否重疊
     *
     * @param int $pharmacyId
     * @param int $dayOfWeek
     * @param string $openTime
     * @param string $closeTime
     * @param int|null $excludeId
     * @return bool
     */
    public function hasOverlappingHours(int $pharmacyId, int $dayOfWeek, string $openTime, string $closeTime, ?int $excludeId = null)
    {
        $query = $this->model->where('pharmacy_id', $pharmacyId)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($query) use ($openTime, $closeTime) {
                $query->where(function ($q) use ($openTime, $closeTime) {
                    $q->where('open_time', '<=', $openTime)
                        ->where('close_time', '>=', $openTime);
                })->orWhere(function ($q) use ($openTime, $closeTime) {
                    $q->where('open_time', '<=', $closeTime)
                        ->where('close_time', '>=', $closeTime);
                })->orWhere(function ($q) use ($openTime, $closeTime) {
                    $q->where('open_time', '>=', $openTime)
                        ->where('close_time', '<=', $closeTime);
                });
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 取得指定時間營業的藥局
     *
     * @param string $time
     * @param int $dayOfWeek
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOpenPharmaciesAtTime(string $time, int $dayOfWeek)
    {
        return $this->model->where('day_of_week', $dayOfWeek)
            ->where('open_time', '<=', $time)
            ->where('close_time', '>=', $time)
            ->with('pharmacy')
            ->get()
            ->pluck('pharmacy');
    }
} 