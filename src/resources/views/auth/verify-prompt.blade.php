@extends('layouts.app')

@section('title', 'メール認証のお願い')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/verify_prompt.css') }}">
@endsection

@section('content')
<main class="verify-page" role="main">
    <section class="verify-card" aria-labelledby="verify-heading">

        <p class="verify-lead" role="status">
            登録していただいたメールアドレスに認証メールを送付しました。<br>
            メール認証を完了してください。
        </p>

        {{-- 上：認証へ（MailHog 等の閲覧導線） --}}
        <a href="{{ route('verify.mailhog') }}" class="btn-primary">認証はこちらから</a>

        {{-- 下：青文字のテキスト形式（POSTで再送） --}}
        <form method="POST" action="{{ route('verification.send') }}" class="resend-form">
            @csrf
            <button type="submit" class="link-like">認証メールを再送する</button>
        </form>
    </section>
</main>
@endsection