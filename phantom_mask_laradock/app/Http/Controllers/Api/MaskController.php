<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaskRequest;
use App\Http\Requests\MaskSearchRequest;
use App\Models\Mask;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="口罩",
 *     description="口罩相關 API"
 * )
 */
class MaskController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/masks",
     *     summary="取得口罩列表",
     *     tags={"口罩"},
     *     @OA\Parameter(
     *         name="pharmacy_id",
     *         in="query",
     *         description="藥局ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="排序方式 (asc/desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="每頁筆數",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得口罩列表",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="True Barrier (green) (3 per pack)"),
     *                     @OA\Property(property="price", type="number", format="float", example=13.7),
     *                     @OA\Property(
     *                         property="pharmacy",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="DFW Wellness")
     *                     )
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
    public function index(MaskRequest $request)
    {
        $validated = $request->validated();
        try {
            $masks = Mask::query()
                ->pharmacy($validated['pharmacy_id'] ?? null)
                ->inStock()
                ->orderByPrice($validated['sort_order'] ?? 'asc')
                ->with('pharmacy')
                ->paginate($validated['per_page'] ?? 15);
            return $this->success($masks);
        } catch (\Exception $e) {
            Log::error('Mask index failed', ['error' => $e->getMessage()]);
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/masks/{id}",
     *     summary="取得單個口罩",
     *     tags={"口罩"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="口罩ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得口罩資訊",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="True Barrier (green) (3 per pack)"),
     *                 @OA\Property(property="price", type="number", format="float", example=13.7),
     *                 @OA\Property(
     *                     property="pharmacy",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="DFW Wellness")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="口罩不存在"
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
            $mask = Mask::with('pharmacy')->findOrFail($id);
            return $this->success($mask);
        } catch (\Exception $e) {
            Log::error('Mask show failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/masks/search",
     *     summary="搜尋口罩",
     *     tags={"口罩"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="搜尋關鍵字",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="每頁筆數",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功搜尋口罩",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="True Barrier (green) (3 per pack)"),
     *                     @OA\Property(property="price", type="number", format="float", example=13.7),
     *                     @OA\Property(
     *                         property="pharmacy",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="DFW Wellness")
     *                     )
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
    public function search(MaskSearchRequest $request)
    {
        $validated = $request->validated();
        $search = $validated['q'];
        try {
            $masks = Mask::query()
                ->search($search)
                ->with('pharmacy')
                ->orderBy('name')
                ->orderBy('name')
                ->paginate($validated['per_page'] ?? 15);
            return $this->success($masks);
        } catch (\Exception $e) {
            Log::error('Mask search failed', ['error' => $e->getMessage()]);
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }
}