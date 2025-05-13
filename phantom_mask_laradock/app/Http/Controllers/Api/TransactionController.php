<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\Mask;
use App\Models\Pharmacy;
use App\Services\TransactionService;
use App\Repositories\TransactionRepository;

/**
 * @OA\Tag(
 *     name="交易",
 *     description="交易相關 API"
 * )
 */
class TransactionController extends Controller
{
    protected $transactionService;
    protected $transactionRepository;

    public function __construct(TransactionService $transactionService, TransactionRepository $transactionRepository)
    {
        $this->transactionService = $transactionService;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * @OA\Get(
     *     path="/api/transactions/top-users",
     *     summary="取得交易排名前 N 名的使用者",
     *     description="根據不同的統計方式（總金額、交易次數、平均金額、口罩數量、最後交易時間）取得排名前 N 名的使用者",
     *     tags={"交易"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="要返回的使用者數量",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="開始日期 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="結束日期 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="排序依據",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"amount", "transaction_count", "avg_amount", "mask_count", "last_transaction"},
     *             default="amount"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="order_by",
     *         in="query",
     *         description="排序方式",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"asc", "desc"},
     *             default="desc"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得使用者排名",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="cash_balance", type="number", format="float", example=1000.50),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=500.75),
     *                     @OA\Property(property="transaction_count", type="integer", example=5),
     *                     @OA\Property(property="avg_amount", type="number", format="float", example=100.15),
     *                     @OA\Property(property="mask_count", type="integer", example=20),
     *                     @OA\Property(property="last_transaction", type="string", format="date-time", example="2024-03-15 10:30:00")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="驗證失敗",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="驗證失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="limit", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="start_date", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="end_date", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="sort_by", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="order_by", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="查詢最高交易金額的使用者失敗"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="錯誤訊息")
     *             )
     *         )
     *     )
     * )
     */
    public function topUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'limit' => 'nullable|integer|min:1|max:100',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'sort_by' => 'nullable|string|in:amount,transaction_count,avg_amount,mask_count,last_transaction',
                'order_by' => 'nullable|string|in:asc,desc'
            ]);

            if ($validator->fails()) {
                Log::warning('Transaction topUsers validation failed', ['errors' => $validator->errors()]);
                return $this->error('驗證失敗', $validator->errors(), 422);
            }

            $users = $this->transactionRepository->getTopUsers(
                $request->input('limit', 10),
                $request->input('start_date'),
                $request->input('end_date'),
                $request->input('sort_by', 'amount'),
                $request->input('order_by', 'desc')
            );

            $result = $users->map(function ($transaction) {
                return [
                    'id' => $transaction->user->id,
                    'name' => $transaction->user->name,
                    'cash_balance' => $transaction->user->cash_balance,
                    'total_amount' => $transaction->sum_amount ?? 0,
                    'transaction_count' => $transaction->count_id ?? 0,
                    'avg_amount' => $transaction->avg_amount ?? 0,
                    'mask_count' => $transaction->sum_quantity ?? 0,
                    'last_transaction' => $transaction->max_transaction_date ?? null
                ];
            });

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('Transaction topUsers failed', ['error' => $e->getMessage()]);
            return $this->error('查詢最高交易金額的使用者失敗', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 取得交易統計
     *
     * @OA\Get(
     *     path="/api/transactions/statistics",
     *     summary="取得交易統計",
     *     tags={"交易"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="開始日期 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="結束日期 (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="pharmacy_id",
     *         in="query",
     *         description="藥局ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="mask_id",
     *         in="query",
     *         description="口罩ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="用戶ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="group_by",
     *         in="query",
     *         description="分組方式 (day/week/month/year)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"day", "week", "month", "year"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(
     *             type="object",
     *             properties={
     *                 @OA\Property(property="summary", type="object", properties={
     *                     @OA\Property(property="total_transactions", type="integer", description="總交易筆數"),
     *                     @OA\Property(property="total_masks", type="integer", description="總口罩數量"),
     *                     @OA\Property(property="total_amount", type="number", format="float", description="總交易金額"),
     *                     @OA\Property(property="average_amount", type="number", format="float", description="平均交易金額"),
     *                     @OA\Property(property="max_amount", type="number", format="float", description="最高交易金額"),
     *                     @OA\Property(property="min_amount", type="number", format="float", description="最低交易金額"),
     *                     @OA\Property(property="average_quantity", type="number", format="float", description="平均購買數量")
     *                 }),
     *                 @OA\Property(property="top_masks", type="array", description="熱門口罩排行", @OA\Items(
     *                     type="object",
     *                     properties={
     *                         @OA\Property(property="mask_id", type="integer", description="口罩ID"),
     *                         @OA\Property(property="mask_name", type="string", description="口罩名稱"),
     *                         @OA\Property(property="total_quantity", type="integer", description="總銷售數量"),
     *                         @OA\Property(property="total_amount", type="number", format="float", description="總銷售金額")
     *                     }
     *                 )),
     *                 @OA\Property(property="top_pharmacies", type="array", description="熱門藥局排行", @OA\Items(
     *                     type="object",
     *                     properties={
     *                         @OA\Property(property="pharmacy_id", type="integer", description="藥局ID"),
     *                         @OA\Property(property="pharmacy_name", type="string", description="藥局名稱"),
     *                         @OA\Property(property="total_transactions", type="integer", description="總交易筆數"),
     *                         @OA\Property(property="total_amount", type="number", format="float", description="總交易金額")
     *                     }
     *                 )),
     *                 @OA\Property(property="time_distribution", type="object", description="交易時段分布", properties={
     *                     @OA\Property(property="morning", type="integer", description="上午 (06:00-12:00)"),
     *                     @OA\Property(property="afternoon", type="integer", description="下午 (12:00-18:00)"),
     *                     @OA\Property(property="evening", type="integer", description="晚上 (18:00-24:00)"),
     *                     @OA\Property(property="night", type="integer", description="凌晨 (00:00-06:00)")
     *                 })
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="伺服器錯誤",
     *         @OA\JsonContent(
     *             type="object",
     *             properties={
     *                 @OA\Property(property="status", type="string", example="error"),
     *                 @OA\Property(property="message", type="string", example="查詢交易統計失敗"),
     *                 @OA\Property(
     *                     property="errors",
     *                     type="object",
     *                     properties={
     *                         @OA\Property(property="error", type="string", example="資料庫查詢錯誤")
     *                     }
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function statistics(Request $request)
    {
        $params = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'pharmacy_id' => $request->input('pharmacy_id'),
            'mask_id' => $request->input('mask_id'),
            'user_id' => $request->input('user_id'),
            'group_by' => $request->input('group_by')
        ];

        return response()->json(
            $this->transactionService->getTransactionStats($params)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/transactions",
     *     summary="建立交易",
     *     tags={"交易"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "pharmacy_id", "mask_id", "quantity"},
     *             @OA\Property(property="user_id", type="integer", example=1, description="用戶 ID"),
     *             @OA\Property(property="pharmacy_id", type="integer", example=1, description="藥局 ID"),
     *             @OA\Property(property="mask_id", type="integer", example=1, description="口罩 ID"),
     *             @OA\Property(property="quantity", type="integer", example=1, description="購買數量", minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="成功建立交易",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="交易成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="pharmacy_id", type="integer", example=1),
     *                 @OA\Property(property="mask_id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=1),
     *                 @OA\Property(property="amount", type="number", format="float", example=10.00),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="庫存不足或餘額不足",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="庫存不足"),
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
     *             @OA\Property(property="message", type="string", example="建立交易失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'mask_id' => 'required|exists:masks,id',
            'quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            Log::warning('Transaction store validation failed', ['errors' => $validator->errors()]);
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $transaction = $this->transactionService->processMaskPurchase($request->all());
            return $this->success($transaction, '交易建立成功', 201);
        } catch (\Exception $e) {
            Log::error('Transaction store failed', ['error' => $e->getMessage()]);
            return $this->error('處理交易失敗', ['error' => $e->getMessage()], 500);
        }
    }
}