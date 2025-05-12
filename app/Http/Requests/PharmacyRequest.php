<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PharmacyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string|max:255',
            'time' => 'nullable|date_format:H:i',
            'day_of_week' => 'nullable|integer|between:0,6',
            'min_price' => 'nullable|numeric|min:0',
            'cash_balance' => 'nullable|numeric|min:0',
        ];
    }

    public function messages()
    {
        return [
            'name.string' => '藥局名稱必須為字串',
            'name.max' => '藥局名稱不能超過 255 個字元',
            'time.date_format' => '時間格式必須為 HH:mm',
            'day_of_week.integer' => '星期必須為整數',
            'day_of_week.between' => '星期必須在 0 到 6 之間',
            'min_price.numeric' => '最低價格必須為數字',
            'min_price.min' => '最低價格不能小於 0',
            'cash_balance.numeric' => '現金餘額必須為數字',
            'cash_balance.min' => '現金餘額不能小於 0',
        ];
    }
} 