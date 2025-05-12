<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * 取得交易列表
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
     * 取得單筆交易
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
     * 建立交易
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