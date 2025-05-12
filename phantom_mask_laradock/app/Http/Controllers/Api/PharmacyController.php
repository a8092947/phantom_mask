<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PharmacyRequest;
use App\Services\PharmacyService;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="藥局",
 *     description="藥局相關 API"
 * )
 */
class PharmacyController extends Controller
{
    protected $pharmacyService;

    public function __construct(PharmacyService $pharmacyService)
    {
        $this->pharmacyService = $pharmacyService;
    }

    /**
     * @OA\Get(
     *     path="/api/pharmacies",
     *     summary="取得藥局列表",
     *     tags={"藥局"},
     *     @OA\Parameter(
     *         name="time",
     *         in="query",
     *         description="查詢時間 (格式: HH:mm)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="day_of_week",
     *         in="query",
     *         description="星期幾 (0-6)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=0, maximum=6)
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="最低價格",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="最高價格",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得藥局列表",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="DFW Wellness"),
     *                     @OA\Property(property="cash_balance", type="number", format="float", example=328.41)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤"
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/pharmacies/{id}",
     *     summary="取得單間藥局",
     *     tags={"藥局"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="藥局ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得藥局資訊",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="DFW Wellness"),
     *                 @OA\Property(property="cash_balance", type="number", format="float", example=328.41)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="藥局不存在"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤"
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/pharmacies/search",
     *     summary="搜尋藥局",
     *     tags={"藥局"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="搜尋關鍵字",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功搜尋藥局",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="DFW Wellness"),
     *                     @OA\Property(property="cash_balance", type="number", format="float", example=328.41)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤"
     *     )
     * )
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