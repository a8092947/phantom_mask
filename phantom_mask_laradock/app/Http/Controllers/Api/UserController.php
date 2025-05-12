<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Requests\UserTopRequest;
use App\Http\Requests\UserSearchRequest;
use App\Models\PharmacyUser;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="用戶",
 *     description="用戶相關 API"
 * )
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="取得用戶列表",
     *     tags={"用戶"},
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
     *         description="成功取得用戶列表",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="balance", type="number", format="float", example=100.50)
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
    public function index(UserRequest $request)
    {
        $validated = $request->validated();
        try {
            $users = PharmacyUser::query()
                ->orderByBalance($validated['sort_order'] ?? 'desc')
                ->paginate($validated['per_page'] ?? 15);
            return $this->success($users);
        } catch (\Exception $e) {
            Log::error('User index failed', ['error' => $e->getMessage()]);
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="取得單個用戶",
     *     tags={"用戶"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="用戶ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得用戶資訊",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="balance", type="number", format="float", example=100.50),
     *                 @OA\Property(
     *                     property="transactions",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="amount", type="number", format="float", example=50.25),
     *                         @OA\Property(property="transaction_date", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="用戶不存在"
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
            $user = PharmacyUser::with(['transactions.mask', 'transactions.pharmacy'])
                ->findOrFail($id);
            return $this->success($user);
        } catch (\Exception $e) {
            Log::error('User show failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/top",
     *     summary="取得消費最高的用戶",
     *     tags={"用戶"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="回傳筆數",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="開始日期",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="結束日期",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得消費最高的用戶",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="total_spent", type="number", format="float", example=1000.50)
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
    public function top(UserTopRequest $request)
    {
        $validated = $request->validated();
        try {
            $users = PharmacyUser::query()
                ->topSpenders(
                    $validated['limit'] ?? 10,
                    $validated['start_date'] ?? null,
                    $validated['end_date'] ?? null
                )
                ->get();
            return $this->success($users);
        } catch (\Exception $e) {
            Log::error('User top failed', ['error' => $e->getMessage()]);
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/search",
     *     summary="搜尋用戶",
     *     tags={"用戶"},
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
     *         description="成功搜尋用戶",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="balance", type="number", format="float", example=100.50)
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
    public function search(UserSearchRequest $request)
    {
        $validated = $request->validated();
        $search = $validated['q'];
        try {
            $users = PharmacyUser::query()
                ->search($search)
                ->orderBy('name')
                ->paginate($validated['per_page'] ?? 15);
            return $this->success($users);
        } catch (\Exception $e) {
            Log::error('User search failed', ['error' => $e->getMessage()]);
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }
}