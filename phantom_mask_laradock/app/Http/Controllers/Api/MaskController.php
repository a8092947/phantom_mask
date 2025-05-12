<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaskRequest;
use App\Http\Requests\MaskSearchRequest;
use App\Models\Mask;
use Illuminate\Support\Facades\Log;

class MaskController extends Controller
{
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