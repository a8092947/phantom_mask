<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'pharmacy_id' => 'nullable|integer|exists:pharmacies,id',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100'
        ];
    }
    public function messages()
    {
        return [
            'pharmacy_id.integer' => '藥局 ID 必須為整數',
            'pharmacy_id.exists' => '藥局不存在',
            'sort_order.in' => '排序方式必須為 asc 或 desc',
            'per_page.integer' => '每頁筆數必須為整數',
            'per_page.min' => '每頁筆數必須大於 0',
            'per_page.max' => '每頁筆數必須小於或等於 100'
        ];
    }
} 