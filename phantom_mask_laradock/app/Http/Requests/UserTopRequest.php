<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserTopRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'limit' => 'nullable|integer|min:1|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ];
    }
    public function messages()
    {
        return [
            'limit.integer' => '名次必須為整數',
            'limit.min' => '名次必須大於 0',
            'limit.max' => '名次必須小於或等於 100',
            'start_date.date' => '開始日期格式錯誤',
            'end_date.date' => '結束日期格式錯誤',
            'end_date.after_or_equal' => '結束日期必須大於或等於開始日期'
        ];
    }
} 