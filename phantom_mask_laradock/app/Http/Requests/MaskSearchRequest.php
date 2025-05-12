<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaskSearchRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'q' => 'required|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ];
    }
    public function messages()
    {
        return [
            'q.required' => '搜尋關鍵字為必填',
            'q.string' => '搜尋關鍵字必須為字串',
            'q.max' => '搜尋關鍵字長度不得超過 255 字',
            'per_page.integer' => '每頁筆數必須為整數',
            'per_page.min' => '每頁筆數必須大於 0',
            'per_page.max' => '每頁筆數必須小於或等於 100'
        ];
    }
} 