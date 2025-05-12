<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PharmacyRequest;
use App\Services\PharmacyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
     *         name="day",
     *         in="query",
     *         description="星期幾 (Mon/Tue/Wed/Thu/Fri/Sat/Sun)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"})
     *     ),
     *     @OA\Parameter(
     *         name="time",
     *         in="query",
     *         description="時間 (HH:mm)",
     *         required=false,
     *         @OA\Schema(type="string", pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$")
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
     *                     @OA\Property(property="cash_balance", type="number", format="float", example=328.41),
     *                     @OA\Property(
     *                         property="opening_hours",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="day", type="integer", example=1),
     *                             @OA\Property(property="open_time", type="string", example="08:00"),
     *                             @OA\Property(property="close_time", type="string", example="17:00")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證錯誤"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'day' => 'nullable|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'time' => 'nullable|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'
        ]);

        if ($validator->fails()) {
            Log::warning('Pharmacy index validation failed', ['errors' => $validator->errors()]);
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $query = Pharmacy::query();

            // 如果有指定星期幾和時間，查詢營業中的藥局
            if ($request->has('day') && $request->has('time')) {
                $dayIndex = $this->getDayIndex($request->day);
                $time = $request->time;

                $query->whereHas('openingHours', function ($q) use ($dayIndex, $time) {
                    $q->where('day', $dayIndex)
                      ->where('open_time', '<=', $time)
                      ->where('close_time', '>=', $time);
                });
            }

            $pharmacies = $query->with('openingHours')->get();

            return $this->success($pharmacies);
        } catch (\Exception $e) {
            Log::error('Pharmacy index failed', ['error' => $e->getMessage()]);
            return $this->error('取得藥局列表失敗', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 取得星期幾的索引
     *
     * @param string $day
     * @return int|null
     */
    private function getDayIndex(string $day): ?int
    {
        $days = [
            'Mon' => 1,
            'Tue' => 2,
            'Wed' => 3,
            'Thu' => 4,
            'Fri' => 5,
            'Sat' => 6,
            'Sun' => 7
        ];

        return $days[$day] ?? null;
    }

    /**
     * @OA\Get(
     *     path="/api/pharmacies/{id}",
     *     summary="取得藥局詳細資訊",
     *     tags={"藥局"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="藥局 ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="排序方式 (name/price)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "price"})
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="排序順序 (asc/desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得藥局詳細資訊",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="DFW Wellness"),
     *                 @OA\Property(property="cash_balance", type="number", format="float", example=328.41),
     *                 @OA\Property(
     *                     property="opening_hours",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="day", type="integer", example=1),
     *                         @OA\Property(property="open_time", type="string", example="08:00"),
     *                         @OA\Property(property="close_time", type="string", example="17:00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="masks",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Black Mask"),
     *                         @OA\Property(property="price", type="number", format="float", example=10.00),
     *                         @OA\Property(property="stock", type="integer", example=100)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="藥局不存在"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證錯誤"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤"
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'sort' => 'nullable|in:name,price',
            'order' => 'nullable|in:asc,desc'
        ]);

        if ($validator->fails()) {
            Log::warning('Pharmacy show validation failed', ['errors' => $validator->errors()]);
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $pharmacy = Pharmacy::with(['openingHours', 'masks' => function ($query) use ($request) {
                if ($request->has('sort')) {
                    $order = $request->input('order', 'asc');
                    $query->orderBy($request->sort, $order);
                }
            }])->findOrFail($id);

            return $this->success($pharmacy);
        } catch (ModelNotFoundException $e) {
            Log::warning('Pharmacy not found', ['id' => $id]);
            return $this->error('藥局不存在', [], 404);
        } catch (\Exception $e) {
            Log::error('Pharmacy show failed', ['error' => $e->getMessage()]);
            return $this->error('取得藥局詳細資訊失敗', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pharmacies/search",
     *     summary="搜尋藥局或口罩",
     *     tags={"藥局"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="搜尋關鍵字",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="搜尋類型 (pharmacy/mask)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pharmacy", "mask"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功搜尋藥局或口罩",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="DFW Wellness"),
     *                     @OA\Property(property="cash_balance", type="number", format="float", example=328.41),
     *                     @OA\Property(
     *                         property="opening_hours",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="day", type="integer", example=1),
     *                             @OA\Property(property="open_time", type="string", example="08:00"),
     *                             @OA\Property(property="close_time", type="string", example="17:00")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="masks",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Black Mask"),
     *                             @OA\Property(property="price", type="number", format="float", example=10.00),
     *                             @OA\Property(property="stock", type="integer", example=100)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證錯誤"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤"
     *     )
     * )
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1',
            'type' => 'nullable|in:pharmacy,mask'
        ]);

        if ($validator->fails()) {
            Log::warning('Pharmacy search validation failed', ['errors' => $validator->errors()]);
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $query = Pharmacy::with(['openingHours', 'masks']);

            // 根據搜尋類型進行搜尋
            if ($request->type === 'pharmacy') {
                $query->where('name', 'like', '%' . $request->q . '%');
            } elseif ($request->type === 'mask') {
                $query->whereHas('masks', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->q . '%');
                });
            } else {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->q . '%')
                      ->orWhereHas('masks', function ($q) use ($request) {
                          $q->where('name', 'like', '%' . $request->q . '%');
                      });
                });
            }

            $pharmacies = $query->get();

            return $this->success($pharmacies);
        } catch (\Exception $e) {
            Log::error('Pharmacy search failed', ['error' => $e->getMessage()]);
            return $this->error('搜尋藥局或口罩失敗', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pharmacies/filter",
     *     summary="查詢特定價格範圍內有指定數量以上口罩的藥局",
     *     tags={"藥局"},
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="最低價格",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="最高價格",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="mask_count",
     *         in="query",
     *         description="最少口罩數量",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1)
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
     *                     @OA\Property(property="cash_balance", type="number", format="float", example=328.41),
     *                     @OA\Property(
     *                         property="opening_hours",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="day", type="integer", example=1),
     *                             @OA\Property(property="open_time", type="string", example="08:00"),
     *                             @OA\Property(property="close_time", type="string", example="17:00")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="masks",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Black Mask"),
     *                             @OA\Property(property="price", type="number", format="float", example=10.00),
     *                             @OA\Property(property="stock", type="integer", example=100)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證錯誤"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤"
     *     )
     * )
     */
    public function filter(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0|gte:min_price',
            'mask_count' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            Log::warning('Pharmacy filter validation failed', ['errors' => $validator->errors()]);
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $query = Pharmacy::with(['openingHours', 'masks']);

            // 如果有指定價格範圍
            if ($request->has('min_price') || $request->has('max_price')) {
                $query->whereHas('masks', function ($q) use ($request) {
                    if ($request->has('min_price')) {
                        $q->where('price', '>=', $request->min_price);
                    }
                    if ($request->has('max_price')) {
                        $q->where('price', '<=', $request->max_price);
                    }
                });
            }

            // 如果有指定最少口罩數量
            if ($request->has('mask_count')) {
                $query->whereHas('masks', function ($q) use ($request) {
                    $q->where('stock', '>=', $request->mask_count);
                }, '>=', $request->mask_count);
            }

            $pharmacies = $query->get();

            return $this->success($pharmacies);
        } catch (\Exception $e) {
            Log::error('Pharmacy filter failed', ['error' => $e->getMessage()]);
            return $this->error('查詢藥局失敗', ['error' => $e->getMessage()], 500);
        }
    }
}