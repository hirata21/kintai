<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">
    @yield('css')
</head>
@php
// 管理画面でナビを非表示にしたいルート群は配列で管理して見通しを良くする
$adminHideNav = request()->routeIs([
'admin.login', 'login.admin', // 管理ログイン系
'login.show', 'login', // 共通ログイン
'register', 'register.store', // 共通登録
'verification.*', 'password.*', 'auth.*',
]);
@endphp

<body>
    <header class="header {{ $adminHideNav ? 'header--auth' : '' }}">
        <div class="header__logo" aria-label="ロゴ">
            <img src="{{ asset('images/logo.svg') }}" alt="ロゴ" class="logo-image">
        </div>

        @unless($adminHideNav)
        <nav class="header__nav" aria-label="メインメニュー">
            <ul class="nav__list">
                <li class="nav__item">
                    @php $active = request()->routeIs('admin.attendances.*'); @endphp
                    <a href="{{ route('admin.attendances.index') }}"
                        class="nav__link {{ $active ? 'is-active' : '' }}"
                        @if($active) aria-current="page" @endif>勤怠一覧</a>
                </li>
                <li class="nav__item">
                    @php $active = request()->routeIs('admin.staff.*'); @endphp
                    <a href="{{ route('admin.staff.index') }}"
                        class="nav__link {{ $active ? 'is-active' : '' }}"
                        @if($active) aria-current="page" @endif>スタッフ一覧</a>
                </li>
                <li class="nav__item">
                    @php $active = request()->routeIs('requests.*'); @endphp
                    <a href="{{ route('requests.index') }}"
                        class="nav__link {{ $active ? 'is-active' : '' }}"
                        @if($active) aria-current="page" @endif>申請一覧</a>
                </li>
                <li class="nav__item">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <input type="hidden" name="admin" value="1">
                        <button type="submit" class="nav__link nav__logout" role="link">ログアウト</button>
                    </form>
                </li>
            </ul>
        </nav>
        @endunless
    </header>

    <main class="admin-main">
        @yield('content')
    </main>

    {{-- scriptsはstackに統一 --}}
    @stack('scripts')
</body>

</html>