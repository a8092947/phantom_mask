<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MaskController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pharmacy_id' => 'nullable|integer|exists:pharmacies,id',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);
        if ($validator->fails()) {
            return $this->error('驗證失敗', $validator->errors(), 422);
        }
        try {
            $masks = Mask::query()
                ->pharmacy($request->pharmacy_id)
                ->inStock()
                ->orderByPrice($request->get('sort_order', 'asc'))
                ->with('pharmacy')
                ->paginate($request->get('per_page', 15));
            return $this->success($masks);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $mask = Mask::with('pharmacy')->findOrFail($id);
            return $this->success($mask);
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
            $masks = Mask::query()
                ->search($search)
                ->with('pharmacy')
                ->orderBy('name')
                ->paginate($request->get('per_page', 15));
            return $this->success($masks);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }
}