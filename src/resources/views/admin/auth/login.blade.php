@extends('layouts.admin')
@section('title', '管理者ログイン画面')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/auth/login.css') }}">
@endsection

@section('content')
<main class="login-page" role="main">
    <div class="login-wrapper">
        <div class="login-container">
            <h1 class="login-heading">管理者ログイン</h1>

            @if (session('status'))
            <p class="flash" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('login.admin') }}" novalidate>
                @csrf
                <input type="hidden" name="login_context" value="admin">

                {{-- メールアドレス --}}
                <div class="form-group form-group--email">
                    <label for="email" class="form-label">メールアドレス</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        class="form-input"
                        @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif
                    >
                    @error('email')
                    <div id="email-error" class="error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                {{-- パスワード --}}
                <div class="form-group form-group--password">
                    <label for="password" class="form-label">パスワード</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="form-input"
                        @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif
                    >
                    @error('password')
                    <div id="password-error" class="error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                {{-- 送信ボタン --}}
                <button type="submit" class="login-button" aria-label="管理者としてログイン">
                    管理者ログインする
                </button>
            </form>
        </div>
    </div>
</main>
@endsection