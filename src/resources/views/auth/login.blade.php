@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
@endsection

@section('title', 'ログイン画面')

@section('content')
<main class="login-wrapper">
    <section class="login-container" aria-labelledby="login-heading">
        <h1 id="login-heading" class="login-heading">ログイン</h1>

        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf

            {{-- メールアドレス --}}
            <div class="form-group form-group--email">
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
            <div class="form-group form-group--password">
                <label for="password" class="form-label">パスワード</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    autocomplete="current-password"
                    required
                    @error('password') aria-describedby="error-password" aria-invalid="true" @enderror>
                @error('password')
                <div class="error" id="error-password" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="login-button">ログインする</button>
        </form>

        <p class="register-link">
            <a href="{{ route('register') }}">会員登録はこちら</a>
        </p>
    </section>
</main>
@endsection