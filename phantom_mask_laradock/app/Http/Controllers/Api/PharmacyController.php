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
     *     summary="取得藥局列表，支援搜尋、過濾和排序",
     *     tags={"藥局"},
     *     @OA\Parameter(
     *         name="day",
     *         in="query",
     *         description="星期幾 (Monday/Tuesday/Wednesday/Thursday/Friday/Saturday/Sunday)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"})
     *     ),
     *     @OA\Parameter(
     *         name="time",
     *         in="query",
     *         description="營業時間篩選 (HH:mm 格式，例如：09:00)。會篩選出在指定時間點正在營業的藥局，即藥局的營業時間範圍包含此時間點",
     *         required=false,
     *         @OA\Schema(type="string", format="time", pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$")
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="搜尋關鍵字（藥局名稱或口罩名稱），結果會按相關性排序：1. 完全匹配 2. 部分匹配 3. 口罩名稱匹配",
     *         required=false,
     *         @OA\Schema(type="string", minLength=1)
     *     ),
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
     *         description="口罩產品種類數量（大於等於此數量的藥局）",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得藥局列表",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="success"),
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
     *                             @OA\Property(property="day", type="string", example="Monday"),
     *                             @OA\Property(property="open", type="string", example="08:00"),
     *                             @OA\Property(property="close", type="string", example="17:00")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="masks",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Black Mask"),
     *                             @OA\Property(property="price", type="number", format="float", example=10.00),
     *                             @OA\Property(property="stock", type="integer", example=100, description="庫存數量")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證錯誤",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="查詢藥局失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'day' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'time' => 'nullable|date_format:H:i',
                'q' => 'nullable|string|min:1',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0|gte:min_price',
                'mask_count' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                Log::warning('Pharmacy index validation failed', ['errors' => $validator->errors()]);
                return $this->error('驗證失敗', $validator->errors(), 422);
            }

            $query = Pharmacy::with(['openingHours', 'masks']);

            // 營業時間篩選
            if ($request->has('day') || $request->has('time')) {
                $query->whereHas('openingHours', function ($q) use ($request) {
                    if ($request->has('day')) {
                        $dayMap = [
                            'Monday' => 0,
                            'Tuesday' => 1,
                            'Wednesday' => 2,
                            'Thursday' => 3,
                            'Friday' => 4,
                            'Saturday' => 5,
                            'Sunday' => 6
                        ];
                        $q->where('day_of_week', $dayMap[$request->day]);
                    }
                    if ($request->has('time')) {
                        $q->where('open_time', '<=', $request->time)
                          ->where('close_time', '>=', $request->time);
                    }
                });
            }

            // 搜尋功能
            if ($request->has('q')) {
                $searchTerm = $request->q;
                $searchWords = explode(' ', $searchTerm);

                $query->where(function ($q) use ($searchWords) {
                    foreach ($searchWords as $word) {
                        $q->orWhere('name', 'LIKE', '%' . $word . '%')
                          ->orWhereHas('masks', function ($q) use ($word) {
                              $q->where('name', 'LIKE', '%' . $word . '%');
                          });
                    }
                });

                // 根據相關性排序
                $query->orderByRaw("
                    CASE 
                        WHEN name LIKE ? THEN 1
                        WHEN name LIKE ? THEN 2
                        WHEN EXISTS (
                            SELECT 1 FROM masks 
                            WHERE masks.pharmacy_id = pharmacies.id 
                            AND masks.name LIKE ?
                        ) THEN 3
                        WHEN EXISTS (
                            SELECT 1 FROM masks 
                            WHERE masks.pharmacy_id = pharmacies.id 
                            AND masks.name LIKE ?
                        ) THEN 4
                        ELSE 5
                    END
                ", [$searchTerm, '%' . $searchTerm . '%', $searchTerm, '%' . $searchTerm . '%']);
            }

            // 價格範圍和口罩數量過濾
            if ($request->has('min_price') || $request->has('max_price') || $request->has('mask_count')) {
                if ($request->has('min_price')) {
                    $query->whereHas('masks', function ($q) use ($request) {
                        $q->where('price', '>=', $request->min_price);
                    });
                }

                if ($request->has('max_price')) {
                    $query->whereHas('masks', function ($q) use ($request) {
                        $q->where('price', '<=', $request->max_price);
                    });
                }

                if ($request->has('mask_count')) {
                    $query->withCount('masks')->having('masks_count', '>=', $request->mask_count);
                }
            }

            $pharmacies = $query->get();

            $result = $pharmacies->map(function ($pharmacy) {
                return [
                    'id' => $pharmacy->id,
                    'name' => $pharmacy->name,
                    'cash_balance' => $pharmacy->cash_balance,
                    'opening_hours' => collect($pharmacy->openingHours)->map(function ($oh) {
                        return [
                            'day' => $oh->day ?? $oh->day_of_week,
                            'open' => isset($oh->open_time) ? (is_string($oh->open_time) ? $oh->open_time : $oh->open_time->format('H:i')) : null,
                            'close' => isset($oh->close_time) ? (is_string($oh->close_time) ? $oh->close_time : $oh->close_time->format('H:i')) : null,
                        ];
                    })->toArray(),
                    'masks' => collect($pharmacy->masks)->map(function ($mask) {
                        return [
                            'id' => $mask->id,
                            'name' => $mask->name,
                            'price' => $mask->price,
                            'stock' => $mask->stock,
                        ];
                    })->toArray(),
                ];
            })->toArray();

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('Pharmacy index failed', ['error' => $e->getMessage()]);
            return $this->error('查詢藥局失敗', ['error' => $e->getMessage()], 500);
        }
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
     *         description="排序欄位，可多選，用逗號分隔。支援的欄位：name（口罩名稱）, price（價格）, stock（庫存）。例如：name,price",
     *         required=false,
     *         @OA\Schema(type="string", pattern="^(name|price|stock)(,(name|price|stock))*$")
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="排序方向，可多選，用逗號分隔。支援的值：asc（升序）, desc（降序）。例如：asc,desc。如果提供的排序方向數量少於排序欄位數量，將使用最後一個排序方向",
     *         required=false,
     *         @OA\Schema(type="string", pattern="^(asc|desc)(,(asc|desc))*$")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得藥局詳細資訊",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="success"),
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
     *                         @OA\Property(property="day", type="string", example="Monday"),
     *                         @OA\Property(property="open", type="string", example="08:00"),
     *                         @OA\Property(property="close", type="string", example="17:00")
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
     *         description="藥局不存在",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="藥局不存在"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證錯誤",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="取得藥局詳細資訊失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'sort' => ['nullable', 'regex:/^(name|price|stock)(,(name|price|stock))*$/'],
            'order' => ['nullable', 'regex:/^(asc|desc)(,(asc|desc))*$/']
        ]);

        if ($validator->fails()) {
            Log::warning('Pharmacy show validation failed', ['errors' => $validator->errors()]);
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $pharmacy = Pharmacy::with(['openingHours', 'masks' => function ($query) use ($request) {
                if ($request->has('sort')) {
                    $sortFields = explode(',', $request->sort);
                    $orderDirections = $request->has('order') ? explode(',', $request->order) : ['asc'];
                    
                    // 如果排序方向數量少於排序欄位數量，使用最後一個排序方向
                    $lastOrder = end($orderDirections);
                    while (count($orderDirections) < count($sortFields)) {
                        $orderDirections[] = $lastOrder;
                    }

                    foreach ($sortFields as $index => $field) {
                        $query->orderBy($field, $orderDirections[$index]);
                    }
                }
            }])->findOrFail($id);

            $result = [
                'id' => $pharmacy->id,
                'name' => $pharmacy->name,
                'cash_balance' => $pharmacy->cash_balance,
                'opening_hours' => collect($pharmacy->openingHours)->map(function ($oh) {
                    return [
                        'day' => $oh->day ?? $oh->day_of_week,
                        'open' => isset($oh->open_time) ? (is_string($oh->open_time) ? $oh->open_time : $oh->open_time->format('H:i')) : null,
                        'close' => isset($oh->close_time) ? (is_string($oh->close_time) ? $oh->close_time : $oh->close_time->format('H:i')) : null,
                    ];
                })->toArray(),
                'masks' => collect($pharmacy->masks)->map(function ($mask) {
                    return [
                        'id' => $mask->id,
                        'name' => $mask->name,
                        'price' => $mask->price,
                        'stock' => $mask->stock,
                    ];
                })->toArray(),
            ];

            return $this->success($result);
        } catch (ModelNotFoundException $e) {
            Log::warning('Pharmacy not found', ['id' => $id]);
            return $this->error('藥局不存在', [], 404);
        } catch (\Exception $e) {
            Log::error('Pharmacy show failed', ['error' => $e->getMessage()]);
            return $this->error('取得藥局詳細資訊失敗', ['error' => $e->getMessage()], 500);
        }
    }
}