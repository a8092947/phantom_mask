<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\Mask;
use App\Models\Pharmacy;

/**
 * @OA\Tag(
 *     name="交易",
 *     description="交易相關 API"
 * )
 */
class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * @OA\Get(
     *     path="/api/transactions/top-users",
     *     summary="查詢最高交易金額的使用者",
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
     *         name="limit",
     *         in="query",
     *         description="回傳筆數",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得使用者列表",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="cash_balance", type="number", format="float", example=1000.00),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=500.00)
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
    public function topUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            Log::warning('Transaction topUsers validation failed', ['errors' => $validator->errors()]);
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $query = User::withSum('transactions as total_amount', 'amount')
                ->orderByDesc('total_amount');

            if ($request->has('start_date')) {
                $query->whereHas('transactions', function ($q) use ($request) {
                    $q->where('created_at', '>=', $request->start_date);
                });
            }

            if ($request->has('end_date')) {
                $query->whereHas('transactions', function ($q) use ($request) {
                    $q->where('created_at', '<=', $request->end_date);
                });
            }

            if ($request->has('limit')) {
                $users = $query->limit($request->limit)->get();
            } else {
                $users = $query->get();
            }

            return $this->success($users);
        } catch (\Exception $e) {
            Log::error('Transaction topUsers failed', ['error' => $e->getMessage()]);
            return $this->error('查詢最高交易金額的使用者失敗', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/transactions/statistics",
     *     summary="查詢交易統計",
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
     *         name="include_details",
     *         in="query",
     *         description="是否包含詳細交易資訊",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得交易統計",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_transactions", type="integer", example=100),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=5000.00),
     *                 @OA\Property(property="average_amount", type="number", format="float", example=50.00),
     *                 @OA\Property(
     *                     property="transactions",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=1),
     *                         @OA\Property(property="pharmacy_id", type="integer", example=1),
     *                         @OA\Property(property="mask_id", type="integer", example=1),
     *                         @OA\Property(property="quantity", type="integer", example=2),
     *                         @OA\Property(property="amount", type="number", format="float", example=20.00),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
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
    public function statistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'include_details' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            Log::warning('Transaction statistics validation failed', ['errors' => $validator->errors()]);
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $query = Transaction::query();

            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            $statistics = [
                'total_transactions' => $query->count(),
                'total_amount' => $query->sum('amount'),
                'average_amount' => $query->avg('amount')
            ];

            if ($request->boolean('include_details')) {
                $statistics['transactions'] = $query->get();
            }

            return $this->success($statistics);
        } catch (\Exception $e) {
            Log::error('Transaction statistics failed', ['error' => $e->getMessage()]);
            return $this->error('查詢交易統計失敗', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/transactions",
     *     summary="處理使用者購買口罩",
     *     tags={"交易"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "pharmacy_id", "mask_id", "quantity"},
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="pharmacy_id", type="integer", example=1),
     *             @OA\Property(property="mask_id", type="integer", example=1),
     *             @OA\Property(property="quantity", type="integer", minimum=1, example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功處理交易",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="pharmacy_id", type="integer", example=1),
     *                 @OA\Property(property="mask_id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=2),
     *                 @OA\Property(property="amount", type="number", format="float", example=20.00),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
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
            $transaction = DB::transaction(function () use ($request) {
                // 檢查庫存
                $mask = Mask::findOrFail($request->mask_id);
                if ($mask->stock < $request->quantity) {
                    throw new \Exception('庫存不足');
                }

                // 檢查使用者餘額
                $user = User::findOrFail($request->user_id);
                $totalAmount = $mask->price * $request->quantity;
                if ($user->cash_balance < $totalAmount) {
                    throw new \Exception('餘額不足');
                }

                // 更新庫存和餘額
                $mask->decrement('stock', $request->quantity);
                $user->decrement('cash_balance', $totalAmount);

                // 建立交易記錄
                return Transaction::create([
                    'user_id' => $request->user_id,
                    'pharmacy_id' => $request->pharmacy_id,
                    'mask_id' => $request->mask_id,
                    'quantity' => $request->quantity,
                    'amount' => $totalAmount
                ]);
            });

            return $this->success($transaction);
        } catch (\Exception $e) {
            Log::error('Transaction store failed', ['error' => $e->getMessage()]);
            return $this->error('處理交易失敗', ['error' => $e->getMessage()], 500);
        }
    }
}