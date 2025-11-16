@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/register.css') }}">
@endsection

@section('title', '会員登録画面')

@section('content')
<main class="register-wrapper">
    <section class="register-container" aria-labelledby="register-heading">
        <h1 id="register-heading" class="register-heading">会員登録</h1>

        {{-- 登録フォーム --}}
        <form method="POST" action="{{ route('register') }}" novalidate>
            @csrf

            {{-- 名前 --}}
            <div class="form-group">
                <label for="name" class="form-label">名前</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-input"
                    value="{{ old('name') }}"
                    autocomplete="name"
                    inputmode="text"
                    required
                    @error('name') aria-describedby="error-name" aria-invalid="true" @enderror>
                @error('name')
                <div class="error" id="error-name" role="alert">{{ $message }}</div>
                @enderror
            </div>

            {{-- メールアドレス --}}
            <div class="form-group">
                <label for="email" class="form-label">メールアドレス</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    inputmode="email"
                    required
                    @error('email') aria-describedby="error-email" aria-invalid="true" @enderror>
                @error('email')
                <div class="error" id="error-email" role="alert">{{ $message }}</div>
                @enderror
            </div>

            {{-- パスワード --}}
            <div class="form-group">
                <label for="password" class="form-label">パスワード</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    autocomplete="new-password"
                    required
                    @error('password') aria-describedby="error-password" aria-invalid="true" @enderror>
                @error('password')
                <div class="error" id="error-password" role="alert">{{ $message }}</div>
                @enderror
            </div>

            {{-- パスワード（確認） --}}
            <div class="form-group">
                <label for="password_confirmation" class="form-label">確認用パスワード</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    class="form-input"
                    autocomplete="new-password"
                    required
                    @error('password_confirmation') aria-describedby="error-password_confirmation" aria-invalid="true" @enderror>
                @error('password_confirmation')
                <div class="error" id="error-password_confirmation" role="alert">{{ $message }}</div>
                @enderror
            </div>

            {{-- 送信 --}}
            <button type="submit" class="register-button">登録する</button>
        </form>

        {{-- ログイン導線 --}}
        <p class="login-link">
            <a href="{{ route('login') }}">ログインはこちら</a>
        </p>
    </section>
</main>
@endsection