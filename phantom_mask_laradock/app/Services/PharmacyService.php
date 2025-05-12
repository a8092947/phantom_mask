<?php

namespace App\Services;

use App\Repositories\PharmacyRepository;
use App\Repositories\OpeningHourRepository;
use Illuminate\Support\Facades\Log;

class PharmacyService
{
    protected $pharmacyRepository;
    protected $openingHourRepository;

    public function __construct(
        PharmacyRepository $pharmacyRepository,
        OpeningHourRepository $openingHourRepository
    ) {
        $this->pharmacyRepository = $pharmacyRepository;
        $this->openingHourRepository = $openingHourRepository;
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
        return $this->openingHourRepository->hasOverlappingHours(
            $pharmacyId,
            $dayOfWeek,
            $openTime,
            $closeTime,
            $excludeId
        );
    }

    /**
     * 取得指定時間營業的藥局
     *
     * @param string|null $time
     * @param int|null $dayOfWeek
     * @param float|null $minPrice
     * @param float|null $maxPrice
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOpenPharmacies(?string $time = null, ?int $dayOfWeek = null, ?float $minPrice = null, ?float $maxPrice = null)
    {
        return $this->pharmacyRepository->getOpenPharmacies(
            $time,
            $dayOfWeek,
            $minPrice,
            $maxPrice
        );
    }

    /**
     * 搜尋藥局
     *
     * @param string $query
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchPharmacies(string $query)
    {
        return $this->pharmacyRepository->searchPharmacies($query);
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
        return $this->pharmacyRepository->getPharmacyMaskStats($minPrice, $maxPrice);
    }
} 