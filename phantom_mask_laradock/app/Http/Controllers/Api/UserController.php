<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PharmacyUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);
        if ($validator->fails()) {
            return $this->error('驗證失敗', $validator->errors(), 422);
        }
        try {
            $users = PharmacyUser::query()
                ->orderByBalance($request->get('sort_order', 'desc'))
                ->paginate($request->get('per_page', 15));
            return $this->success($users);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $user = PharmacyUser::with(['transactions.mask', 'transactions.pharmacy'])
                ->findOrFail($id);
            return $this->success($user);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    public function top(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);
        if ($validator->fails()) {
            return $this->error('驗證失敗', $validator->errors(), 422);
        }
        try {
            $users = PharmacyUser::query()
                ->topSpenders(
                    $request->get('limit', 10),
                    $request->get('start_date'),
                    $request->get('end_date')
                )
                ->get();
            return $this->success($users);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);
        if ($validator->fails()) {
            return $this->error('驗證失敗', $validator->errors(), 422);
        }
        $search = $request->get('q');
        try {
            $users = PharmacyUser::query()
                ->search($search)
                ->orderBy('name')
                ->paginate($request->get('per_page', 15));
            return $this->success($users);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }
}