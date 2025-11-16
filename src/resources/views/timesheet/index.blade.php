@extends('layouts.app')
@section('title', '勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/timesheet/index.css') }}">
@endsection

@section('content')
@php \Carbon\Carbon::setLocale('ja'); @endphp

<main class="ts-page" role="main" aria-labelledby="ts-heading">
    {{-- タイトル --}}
    <h1 id="ts-heading" class="ts-page-title">勤怠一覧</h1>

    {{-- ▼ カード1：月切替バー --}}
    <section class="ts-card-toolbar" aria-label="月の切り替え">
        <div class="ts-toolbar" role="group" aria-label="月切替">
            {{-- 前月 --}}
            <a
                class="ts-nav ts-nav--prev"
                href="{{ route('timesheet.index', ['month' => $prevYm]) }}"
                aria-label="前月（{{ \Carbon\Carbon::createFromFormat('Y-m', $prevYm)->format('Y年n月') }}）へ">
                <img src="{{ asset('images/arrow.png') }}" alt="" aria-hidden="true" class="ts-nav__icon ts-nav__icon--left">
                <span class="ts-nav__text">前月</span>
            </a>

            {{-- 現在の年月 --}}
            <div class="ts-current-month" aria-live="polite">
                <img src="{{ asset('images/calendar.png') }}" alt="" aria-hidden="true" class="ts-current-month__icon-img">
                @php
                $ymText = $ym->format('Y/m');
                $ymIso = $ym->format('Y-m');
                @endphp
                <time class="ts-current-month__text" datetime="{{ $ymIso }}">{{ $ymText }}</time>
            </div>

            {{-- 翌月 --}}
            <a
                class="ts-nav ts-nav--next"
                href="{{ route('timesheet.index', ['month' => $nextYm]) }}"
                aria-label="翌月（{{ \Carbon\Carbon::createFromFormat('Y-m', $nextYm)->format('Y年n月') }}）へ">
                <span class="ts-nav__text">翌月</span>
                <img src="{{ asset('images/arrow.png') }}" alt="" aria-hidden="true" class="ts-nav__icon ts-nav__icon--right">
            </a>
        </div>
    </section>

    {{-- ▼ カード2：テーブル --}}
    <section class="ts-card-table" aria-labelledby="ts-table-caption">
        <div class="ts-table-wrap">
            <table class="ts-table">
                <caption id="ts-table-caption" class="sr-only">選択中の月の勤怠一覧</caption>
                <thead>
                    <tr>
                        <th scope="col">日付</th>
                        <th scope="col">出勤</th>
                        <th scope="col">退勤</th>
                        <th scope="col">休憩</th>
                        <th scope="col">合計</th>
                        <th scope="col">詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                    @php
                    $date = $r->date;
                    $in = $r->in ?? null;
                    $out = $r->out ?? null;
                    $breakMin = property_exists($r, 'break') ? $r->break : null;
                    $workMin = property_exists($r, 'work') ? $r->work : null;

                    // 表示: 06/01(木)
                    $dateLabel = $date instanceof \Carbon\Carbon
                    ? $date->locale('ja')->isoFormat('MM/DD(ddd)')
                    : (string) $date;

                    // ISO: 2025-06-01
                    $dateIso = $date instanceof \Carbon\Carbon
                    ? $date->format('Y-m-d')
                    : (is_string($date) ? $date : null);

                    // 詳細リンク
                    $detailUrl = route(
                    'timesheet.showByDate',
                    $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date
                    );

                    // 分→H:MM
                    $fmt = function (?int $minutes) {
                    if ($minutes === null) return '';
                    $m = max(0, (int) $minutes);
                    return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
                    };
                    @endphp

                    <tr>
                        <td class="ts-col-date">
                            @if ($dateIso)
                            <time datetime="{{ $dateIso }}">{{ $dateLabel }}</time>
                            @else
                            {{ $dateLabel }}
                            @endif
                        </td>
                        <td>{{ $in ?? '' }}</td>
                        <td>{{ $out ?? '' }}</td>
                        <td class="ts-col-num">{{ $fmt($breakMin) }}</td>
                        <td class="ts-col-num">{{ $fmt($workMin) }}</td>
                        <td class="ts-col-detail">
                            <a class="ts-link" href="{{ $detailUrl }}" aria-label="詳細（{{ $dateLabel }}）">詳細</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="ts-empty">データがありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection