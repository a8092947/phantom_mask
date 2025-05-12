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

/**
 * @OA\Tag(
 *     name="交易",
 *     description="交易相關 API"
 * )
 */
class TransactionController extends Controller
{
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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="success"),
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
     *             @OA\Property(property="message", type="string", example="查詢最高交易金額的使用者失敗"),
     *             @OA\Property(property="errors", type="object")
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
                'end_date' => 'nullable|date|after_or_equal:start_date'
            ]);

            if ($validator->fails()) {
                Log::warning('Transaction topUsers validation failed', ['errors' => $validator->errors()]);
                return $this->error('驗證失敗', $validator->errors(), 422);
            }

            $query = User::withSum('transactions', 'amount')
                        ->orderBy('transactions_sum_amount', 'desc');

            if ($request->has('start_date') || $request->has('end_date')) {
                $query->whereHas('transactions', function ($q) use ($request) {
                    if ($request->has('start_date')) {
                        $q->where('created_at', '>=', $request->start_date);
                    }
                    if ($request->has('end_date')) {
                        $q->where('created_at', '<=', $request->end_date . ' 23:59:59');
                    }
                });
            }

            $users = $query->take($request->limit ?? 10)->get();

            $result = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'cash_balance' => $user->cash_balance,
                    'total_amount' => $user->transactions_sum_amount ?? 0
                ];
            });

            return $this->success($result);
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
     *     @OA\Response(
     *         response=200,
     *         description="成功取得交易統計",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_transactions", type="integer", example=100),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=5000.00),
     *                 @OA\Property(property="average_amount", type="number", format="float", example=50.00)
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
     *             @OA\Property(property="message", type="string", example="查詢交易統計失敗"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function statistics(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date'
            ]);

            if ($validator->fails()) {
                Log::warning('Transaction statistics validation failed', ['errors' => $validator->errors()]);
                return $this->error('驗證失敗', $validator->errors(), 422);
            }

            $query = Transaction::query();

            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }

            $totalTransactions = $query->count();
            $totalAmount = $query->sum('amount');
            $averageAmount = $totalTransactions > 0 ? $totalAmount / $totalTransactions : 0;

            $result = [
                'total_transactions' => $totalTransactions,
                'total_amount' => $totalAmount,
                'average_amount' => round($averageAmount, 2)
            ];

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('Transaction statistics failed', ['error' => $e->getMessage()]);
            return $this->error('查詢交易統計失敗', ['error' => $e->getMessage()], 500);
        }
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
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="pharmacy_id", type="integer", example=1),
     *             @OA\Property(property="mask_id", type="integer", example=1),
     *             @OA\Property(property="quantity", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="成功建立交易",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="交易建立成功"),
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
     *             @OA\Property(property="message", type="string", example="處理交易失敗"),
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
            $transaction = DB::transaction(function () use ($request) {
                $mask = Mask::findOrFail($request->mask_id);
                if ($mask->stock < $request->quantity) {
                    throw new \Exception('庫存不足');
                }

                $user = User::findOrFail($request->user_id);
                $totalAmount = $mask->price * $request->quantity;
                if ($user->cash_balance < $totalAmount) {
                    throw new \Exception('餘額不足');
                }

                $mask->decrement('stock', $request->quantity);
                $user->decrement('cash_balance', $totalAmount);

                return Transaction::create([
                    'user_id' => $request->user_id,
                    'pharmacy_id' => $request->pharmacy_id,
                    'mask_id' => $request->mask_id,
                    'quantity' => $request->quantity,
                    'amount' => $totalAmount
                ]);
            });

            return $this->success($transaction, '交易建立成功', 201);
        } catch (\Exception $e) {
            Log::error('Transaction store failed', ['error' => $e->getMessage()]);
            return $this->error('處理交易失敗', ['error' => $e->getMessage()], 500);
        }
    }
}