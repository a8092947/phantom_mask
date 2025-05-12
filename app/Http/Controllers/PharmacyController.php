<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Http\Requests\PharmacyRequest;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class PharmacyController extends Controller
{
    /**
     * 取得藥局列表
     * 
     * @param PharmacyRequest $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/pharmacies",
     *     summary="取得藥局列表",
     *     tags={"藥局"},
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="藥局名稱",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="time",
     *         in="query",
     *         description="營業時間 (HH:mm)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="day_of_week",
     *         in="query",
     *         description="星期 (0-6)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="最低價格",
     *         required=false,
     *         @OA\Schema(type="number")
     *     ),
     *     @OA\Parameter(
     *         name="cash_balance",
     *         in="query",
     *         description="現金餘額",
     *         required=false,
     *         @OA\Schema(type="number")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Pharmacy")),
     *                 @OA\Property(property="total", type="integer")
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
            Log::info('開始查詢藥局列表', ['params' => $request->all()]);
            
            $query = Pharmacy::with(['openingHours', 'masks']);

            // 根據名稱搜尋
            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            // 根據營業時間篩選
            if ($request->has('time') && $request->has('day_of_week')) {
                $query->whereHas('openingHours', function (Builder $query) use ($request) {
                    $query->where('day_of_week', $request->day_of_week)
                        ->where('open_time', '<=', $request->time)
                        ->where('close_time', '>=', $request->time);
                });
            }

            // 根據最低價格篩選
            if ($request->has('min_price')) {
                $query->whereHas('masks', function (Builder $query) use ($request) {
                    $query->where('price', '>=', $request->min_price);
                });
            }

            // 根據現金餘額篩選
            if ($request->has('cash_balance')) {
                $query->where('cash_balance', '>=', $request->cash_balance);
            }

            $pharmacies = $query->paginate(10);
            
            Log::info('藥局列表查詢完成', ['count' => $pharmacies->total()]);

            return response()->json([
                'status' => 'success',
                'data' => $pharmacies
            ]);
        } catch (\Exception $e) {
            Log::error('查詢藥局列表時發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => '查詢藥局時發生錯誤'
            ], 500);
        }
    }

    /**
     * 取得特定藥局資料
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/pharmacies/{id}",
     *     summary="取得特定藥局資料",
     *     tags={"藥局"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="藥局 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Pharmacy")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="找不到藥局"
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
            Log::info('開始查詢特定藥局', ['id' => $id]);
            
            $pharmacy = Pharmacy::with(['openingHours', 'masks'])
                ->findOrFail($id);

            Log::info('藥局查詢完成', ['id' => $id]);
            
            return response()->json([
                'status' => 'success',
                'data' => $pharmacy
            ]);
        } catch (\Exception $e) {
            Log::error('查詢特定藥局時發生錯誤', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => '查詢藥局時發生錯誤'
            ], 500);
        }
    }
} 