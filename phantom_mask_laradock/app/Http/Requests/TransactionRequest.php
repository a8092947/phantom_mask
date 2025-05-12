<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
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
            'user_id' => 'required|integer|exists:users,id',
            'pharmacy_id' => 'required|integer|exists:pharmacies,id',
            'mask_id' => 'required|integer|exists:masks,id',
            'amount' => 'required|numeric|min:0'
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
            'user_id.required' => '使用者 ID 為必填',
            'user_id.integer' => '使用者 ID 必須為整數',
            'user_id.exists' => '使用者不存在',
            'pharmacy_id.required' => '藥局 ID 為必填',
            'pharmacy_id.integer' => '藥局 ID 必須為整數',
            'pharmacy_id.exists' => '藥局不存在',
            'mask_id.required' => '口罩 ID 為必填',
            'mask_id.integer' => '口罩 ID 必須為整數',
            'mask_id.exists' => '口罩不存在',
            'amount.required' => '金額為必填',
            'amount.numeric' => '金額必須為數字',
            'amount.min' => '金額必須大於或等於 0'
        ];
    }
} 