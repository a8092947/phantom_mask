<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Mask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:pharmacy_users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);
        if ($validator->fails()) {
            return $this->error('驗證失敗', $validator->errors(), 422);
        }
        try {
            $transactions = Transaction::query()
                ->user($request->user_id)
                ->dateRange($request->start_date, $request->end_date)
                ->orderByAmount($request->get('sort_order', 'desc'))
                ->with(['user', 'pharmacy', 'mask'])
                ->paginate($request->get('per_page', 15));
            return $this->success($transactions);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $transaction = Transaction::with(['user', 'pharmacy', 'mask'])
                ->findOrFail($id);
            return $this->success($transaction);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:pharmacy_users,id',
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'mask_id' => 'required|exists:masks,id',
            'amount' => 'required|numeric|min:0'
        ]);
        if ($validator->fails()) {
            return $this->error('驗證失敗', $validator->errors(), 422);
        }
        $mask = Mask::where('id', $request->mask_id)
                   ->where('pharmacy_id', $request->pharmacy_id)
                   ->first();
        if (!$mask) {
            return $this->error('指定的口罩不屬於該藥局', null, 422);
        }
        if ($mask->price != $request->amount) {
            return $this->error('交易金額與口罩價格不符', null, 422);
        }
        $transaction = Transaction::create([
            'user_id' => $request->user_id,
            'pharmacy_id' => $request->pharmacy_id,
            'mask_id' => $request->mask_id,
            'amount' => $request->amount,
            'quantity' => 1
        ]);
        try {
            $transaction->processTransaction();
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, 422);
        }
        return $this->success($transaction, '交易建立成功', 201);
    }
}