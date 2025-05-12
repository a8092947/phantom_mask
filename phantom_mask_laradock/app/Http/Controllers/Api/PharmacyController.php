<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class PharmacyController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'time' => 'nullable|date_format:H:i',
            'day_of_week' => 'nullable|integer|between:1,7',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0|gte:min_price',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return $this->error('驗證失敗', $validator->errors(), 422);
        }

        try {
            $pharmacies = Pharmacy::query()
                ->when($request->time && $request->day_of_week, function ($query) use ($request) {
                    $dateTime = Carbon::parse($request->time);
                    $dateTime->setDayOfWeek($request->day_of_week);
                    return $query->openAt($dateTime);
                })
                ->when($request->min_price || $request->max_price, function ($query) use ($request) {
                    return $query->priceRange($request->min_price, $request->max_price);
                })
                ->with(['openingHours', 'masks'])
                ->paginate($request->get('per_page', 15));

            return $this->success($pharmacies);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $pharmacy = Pharmacy::with(['openingHours', 'masks'])
                ->findOrFail($id);

            return $this->success($pharmacy);
        } catch (\Exception $e) {
            return $this->error('查詢失敗：' . $e->getMessage());
        }
    }
}