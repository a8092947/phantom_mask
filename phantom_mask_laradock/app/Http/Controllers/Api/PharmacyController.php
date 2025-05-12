<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PharmacyRequest;
use App\Services\PharmacyService;
use Illuminate\Support\Facades\Log;

class PharmacyController extends Controller
{
    protected $pharmacyService;

    public function __construct(PharmacyService $pharmacyService)
    {
        $this->pharmacyService = $pharmacyService;
    }

    /**
     * 取得藥局列表
     */
    public function index(PharmacyRequest $request)
    {
        try {
            $validated = $request->validated();
            $pharmacies = $this->pharmacyService->getOpenPharmacies(
                $validated['time'] ?? null,
                $validated['day_of_week'] ?? null,
                $validated['min_price'] ?? null,
                $validated['max_price'] ?? null
            );
            return $this->success($pharmacies);
        } catch (\Exception $e) {
            Log::error('Pharmacy index failed', ['error' => $e->getMessage()]);
            return $this->error('取得藥局列表失敗', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 取得單間藥局
     */
    public function show($id)
    {
        try {
            $pharmacy = $this->pharmacyService->getOpenPharmacies(null, null, null, null)
                ->where('id', $id)
                ->first();

            if (!$pharmacy) {
                Log::warning('Pharmacy not found', ['id' => $id]);
                return $this->error('藥局不存在', null, 404);
            }

            return $this->success($pharmacy);
        } catch (\Exception $e) {
            Log::error('Pharmacy show failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->error('取得藥局失敗', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 搜尋藥局
     */
    public function search(PharmacyRequest $request)
    {
        try {
            $validated = $request->validated();
            $pharmacies = $this->pharmacyService->searchPharmacies($validated['q']);
            return $this->success($pharmacies);
        } catch (\Exception $e) {
            Log::error('Pharmacy search failed', ['error' => $e->getMessage()]);
            return $this->error('搜尋藥局失敗', ['error' => $e->getMessage()], 500);
        }
    }
}