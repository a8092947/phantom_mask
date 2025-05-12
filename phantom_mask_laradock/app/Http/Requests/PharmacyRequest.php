<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PharmacyRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限進行此請求
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * 取得驗證規則
     *
     * @return array
     */
    public function rules()
    {
        return [
            'time' => 'nullable|date_format:H:i',
            'day_of_week' => 'nullable|integer|between:1,7',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0|gte:min_price',
            'per_page' => 'nullable|integer|min:1|max:100',
            'q' => 'required_if:action,search|string|min:1'
        ];
    }

    /**
     * 取得驗證錯誤訊息
     *
     * @return array
     */
    public function messages()
    {
        return [
            'time.date_format' => '時間格式必須為 HH:mm',
            'day_of_week.integer' => '星期必須為整數',
            'day_of_week.between' => '星期必須在 1 到 7 之間',
            'min_price.numeric' => '最低價格必須為數字',
            'min_price.min' => '最低價格必須大於或等於 0',
            'max_price.numeric' => '最高價格必須為數字',
            'max_price.min' => '最高價格必須大於或等於 0',
            'max_price.gte' => '最高價格必須大於或等於最低價格',
            'per_page.integer' => '每頁筆數必須為整數',
            'per_page.min' => '每頁筆數必須大於或等於 1',
            'per_page.max' => '每頁筆數必須小於或等於 100',
            'q.required_if' => '搜尋關鍵字為必填',
            'q.string' => '搜尋關鍵字必須為字串',
            'q.min' => '搜尋關鍵字長度必須大於或等於 1'
        ];
    }
} 