<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 基本ルール:
     * - email は必須で、usersに存在すること
     * - password は必須
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                // メール形式を見ない仕様なら 'email' はあえて付けない
                Rule::exists('users', 'email'),
            ],
            'password' => ['required'],
        ];
    }

    /**
     * ルールが全部通ったあとに、
     * 実際にユーザーのパスワードと一致するかを追加で見る。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // ここまでで必須・exists で落ちてたらパスワードチェックはしない
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $email    = $this->input('email');
            $password = $this->input('password');

            /** @var \App\Models\User|null $user */
            $user = User::where('email', $email)->first();

            // existsを書いているので基本的には見つかるはずだが、
            // レースコンディションなどがあっても安全に落とす
            if (! $user || ! Hash::check($password, $user->password)) {
                $validator->errors()->add('email', 'ログイン情報が登録されていません');
            }

            // 将来「email_verified_atがnullならNG」「status=inactiveならNG」とかを入れたくなったらここに足す
        });
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'メールアドレスを入力してください',
            'email.exists'      => 'ログイン情報が登録されていません',
            'password.required' => 'パスワードを入力してください',
        ];
    }

    public function attributes(): array
    {
        return [
            'email'    => 'メールアドレス',
            'password' => 'パスワード',
        ];
    }
}