<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">
    @yield('css')
</head>
@php
$isAuthPage = request()->routeIs([
'login.show','register',
'password.*','verification.*',
'auth.login*','auth.register*','auth.*',
]);
@endphp

<body>
    <header class="header {{ $isAuthPage ? 'header--auth' : '' }}">
        <div class="header__logo" aria-label="ロゴ">
            <img src="{{ asset('images/logo.svg') }}" alt="ロゴ" class="logo-image">
        </div>

        @unless ($isAuthPage)
        <nav class="header__nav" aria-label="メインメニュー">
            <ul class="nav__list">
                <li class="nav__item">
                    @php $active = request()->routeIs('punch.*'); @endphp
                    <a href="{{ route('punch.show') }}"
                        class="nav__link {{ $active ? 'is-active' : '' }}"
                        @if($active) aria-current="page" @endif>勤怠</a>
                </li>
                <li class="nav__item">
                    @php $active = request()->routeIs('timesheet.*'); @endphp
                    <a href="{{ route('timesheet.index') }}"
                        class="nav__link {{ $active ? 'is-active' : '' }}"
                        @if($active) aria-current="page" @endif>勤怠一覧</a>
                </li>
                <li class="nav__item">
                    @php $active = request()->routeIs('requests.*'); @endphp
                    <a href="{{ route('requests.index') }}"
                        class="nav__link {{ $active ? 'is-active' : '' }}"
                        @if($active) aria-current="page" @endif>申請</a>
                </li>
                <li class="nav__item">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav__link nav__logout" role="link">ログアウト</button>
                    </form>
                </li>
            </ul>
        </nav>
        @endunless
    </header>

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>

</html>