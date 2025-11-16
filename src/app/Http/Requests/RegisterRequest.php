<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 1. お名前が未入力の場合 → 専用メッセージを出す
            'name' => ['required'],

            // 2. メールアドレスが未入力の場合 → 専用メッセージを出す
            'email' => ['required'],

            // 3. パスワードが未入力 or 8文字未満 or 確認と不一致
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            // 1. 未入力の場合
            'name.required'     => 'お名前を入力してください',
            'email.required'    => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',

            // 2. パスワードの入力規則違反の場合
            'password.min'      => 'パスワードは8文字以上で入力してください',

            // 3. 確認用パスワードの入力規則違反の場合
            'password.confirmed' => 'パスワードと一致しません',
        ];
    }
}
