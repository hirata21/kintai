@extends('layouts.app')

@section('title', '出勤登録')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance/punch.css') }}">
@endsection

@section('content')
@php
\Carbon\Carbon::setLocale('ja');

// 日付表示（当日 or 対象勤怠レコードの日付）
$today = (isset($attendance) ? $attendance->work_date : now())->translatedFormat('Y年n月j日(D)');

// ステータス表示用ラベル
if (!empty($isClockedOut) && $isClockedOut) {
$workStatusLabel = '退勤済';
} elseif (!empty($isOnBreak) && $isOnBreak) {
$workStatusLabel = '休憩中';
} elseif (!empty($isClockedIn) && $isClockedIn) {
$workStatusLabel = '出勤中';
} else {
$workStatusLabel = '勤務外';
}
@endphp

<main class="punch-page" role="main">
    <section class="punch-card" aria-labelledby="punch-heading">
        <header class="punch-card__head">

            <span class="punch-badge" aria-live="polite">{{ $workStatusLabel }}</span>

            <p class="punch-date" aria-label="本日">{{ $today }}</p>
            <time id="clock" class="punch-time" aria-live="polite">{{ now()->format('H:i') }}</time>
        </header>

        <div class="punch-card__body">
            {{-- 未出勤：出勤ボタンのみ --}}
            @if (!$isClockedIn && !$isClockedOut)
            <form method="POST" action="{{ route('punch.in') }}">
                @csrf
                <button class="punch-btn" type="submit">出勤</button>
            </form>

            {{-- 勤務中：退勤＋休憩入 --}}
            @elseif ($isClockedIn && !$isOnBreak && !$isClockedOut)
            <div class="punch-card__actions" role="group" aria-label="打刻操作">
                <form method="POST" action="{{ route('punch.out') }}">
                    @csrf
                    <button class="punch-btn" type="submit">退勤</button>
                </form>
                <form method="POST" action="{{ route('punch.break.in') }}">
                    @csrf
                    <button class="punch-btn punch-btn--secondary" type="submit">休憩入</button>
                </form>
            </div>

            {{-- 休憩中：休憩戻のみ --}}
            @elseif ($isClockedIn && $isOnBreak && !$isClockedOut)
            <form method="POST" action="{{ route('punch.break.out') }}">
                @csrf
                <button class="punch-btn punch-btn--secondary" type="submit">休憩戻</button>
            </form>

            {{-- 退勤後：お疲れ様テキストのみ --}}
            @else
            <p class="punch-message" role="status">お疲れ様でした。</p>
            @endif
        </div>
    </section>
</main>

@push('scripts')
<script>
    (() => {
        const el = document.getElementById('clock');
        if (!el) return;

        const tick = () => {
            const d = new Date();
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');
            el.textContent = `${hh}:${mm}`;
        };

        tick();
        setInterval(tick, 15 * 1000);
    })();
</script>
@endpush
@endsection