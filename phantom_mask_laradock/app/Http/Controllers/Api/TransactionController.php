<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
     *     path="/api/transactions",
     *     summary="取得交易列表",
     *     tags={"交易"},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="用戶ID",
     *         required=false,
     *         @OA\Schema(type="integer")
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
     *         description="成功取得交易列表",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="amount", type="number", format="float", example=50.25),
     *                     @OA\Property(property="transaction_date", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(
     *                         property="pharmacy",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="DFW Wellness")
     *                     ),
     *                     @OA\Property(
     *                         property="mask",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="True Barrier (green) (3 per pack)")
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
            'user_id' => 'nullable|integer|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            Log::warning('Transaction index validation failed', ['errors' => $validator->errors()]);
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $query = Transaction::query();

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('start_date')) {
                $query->where('transaction_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('transaction_date', '<=', $request->end_date);
            }

            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy('amount', $sortOrder);

            $perPage = $request->get('per_page', 15);
            $transactions = $query->with(['user', 'pharmacy', 'mask'])->paginate($perPage);

            return $this->success($transactions);
        } catch (\Exception $e) {
            Log::error('Transaction index failed', ['error' => $e->getMessage()]);
            return $this->error('取得交易列表失敗', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/transactions/{id}",
     *     summary="取得單筆交易",
     *     tags={"交易"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="交易ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功取得交易資訊",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="amount", type="number", format="float", example=50.25),
     *                 @OA\Property(property="transaction_date", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(
     *                     property="pharmacy",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="DFW Wellness")
     *                 ),
     *                 @OA\Property(
     *                     property="mask",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="True Barrier (green) (3 per pack)")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="交易不存在"
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
            $transaction = Transaction::with(['user', 'pharmacy', 'mask'])->findOrFail($id);
            return $this->success($transaction);
        } catch (\Exception $e) {
            Log::error('Transaction show failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->error('取得交易失敗', ['error' => $e->getMessage()], 404);
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
     *             @OA\Property(property="quantity", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="交易建立成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="交易建立成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="amount", type="number", format="float", example=50.25),
     *                 @OA\Property(property="transaction_date", type="string", format="date-time")
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
    public function store(TransactionRequest $request)
    {
        try {
            $transaction = $this->transactionService->processTransaction($request->validated());
            Log::info('Transaction created', ['id' => $transaction->id]);
            return $this->success($transaction, '交易建立成功', 201);
        } catch (\Exception $e) {
            Log::error('Transaction store failed', ['error' => $e->getMessage()]);
            return $this->error('交易建立失敗', ['error' => $e->getMessage()], 422);
        }
    }
}