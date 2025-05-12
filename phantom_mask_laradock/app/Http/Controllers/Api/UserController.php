<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Requests\UserTopRequest;
use App\Http\Requests\UserSearchRequest;
use App\Models\PharmacyUser;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
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