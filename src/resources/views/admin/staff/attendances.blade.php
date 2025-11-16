@extends('layouts.admin')
@section('title', '管理：スタッフ別勤怠')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/staff/attendances.css') }}">
@endsection

@section('content')
@php
/**
* Controller 側で用意する想定:
* $user, $ym, $prevYm, $nextYm, $rows
*/
\Carbon\Carbon::setLocale('ja');
@endphp

<div class="ts-page">
    <div class="ts-page-inner">

        {{-- タイトル --}}
        <h1 class="ts-page-title">{{ $user->name }}さんの勤怠</h1>

        {{-- 月切替 --}}
        <section class="ts-card-toolbar">
            <nav class="ts-toolbar" aria-label="月切替">
                {{-- 前月 --}}
                <a class="ts-nav ts-nav--prev"
                    href="{{ route('admin.staff.attendances', [$user->id, 'month' => $prevYm]) }}">
                    <img src="{{ asset('images/arrow.png') }}" alt="" aria-hidden="true" class="ts-nav__icon ts-nav__icon--left">
                    <span class="ts-nav__text">前月</span>
                </a>

                {{-- 今月 --}}
                <div class="ts-current-month">
                    <img src="{{ asset('images/calendar.png') }}" alt="" aria-hidden="true" class="ts-current-month__icon-img">
                    <span class="ts-current-month__text">{{ $ym->format('Y') }}/{{ $ym->format('m') }}</span>
                </div>

                {{-- 翌月 --}}
                <a class="ts-nav ts-nav--next"
                    href="{{ route('admin.staff.attendances', [$user->id, 'month' => $nextYm]) }}">
                    <span class="ts-nav__text">翌月</span>
                    <img src="{{ asset('images/arrow.png') }}" alt="" aria-hidden="true" class="ts-nav__icon ts-nav__icon--right">
                </a>
            </nav>
        </section>

        {{-- テーブル --}}
        <section class="ts-card-table">
            <div class="ts-table-wrap">
                <table class="ts-table">
                    <caption class="sr-only">{{ $user->name }}さんの月次勤怠一覧（出勤・退勤・休憩・合計・詳細）</caption>
                    <thead>
                        <tr>
                            <th scope="col">日付</th>
                            <th scope="col">出勤</th>
                            <th scope="col">退勤</th>
                            <th scope="col" class="ts-col-numhead">休憩</th>
                            <th scope="col" class="ts-col-numhead">合計</th>
                            <th scope="col" class="ts-col-detailhead">詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                        @php
                        // 日付表示
                        $dateObj = $r->date;
                        $dateLabel = $dateObj instanceof \Carbon\Carbon
                        ? $dateObj->locale('ja')->isoFormat('MM/DD(ddd)')
                        : (string) $dateObj;

                        // 出勤・退勤
                        $in = $r->in ?? '';
                        $out = $r->out ?? '';

                        // 分→H:MM
                        $fmtHm = function (?int $minutes) {
                        if ($minutes === null) return '';
                        $m = max(0, (int) $minutes);
                        return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
                        };

                        $breakMin = property_exists($r, 'break') ? $r->break : null;
                        $workMin = property_exists($r, 'work') ? $r->work : null;
                        $attendanceId = $r->attendance_id ?? null;

                        // showByUserAndDate 用に YYYY-MM-DD を確定
                        $dateForUrl = $dateObj instanceof \Carbon\Carbon
                        ? $dateObj->toDateString()
                        : (string) $dateObj;
                        @endphp

                        <tr>
                            <td class="ts-col-date">{{ $dateLabel }}</td>
                            <td>{{ $in }}</td>
                            <td>{{ $out }}</td>
                            <td class="ts-col-num">{{ $fmtHm($breakMin) }}</td>
                            <td class="ts-col-num">{{ $fmtHm($workMin) }}</td>
                            <td class="ts-col-detail">
                                @if ($attendanceId && Route::has('admin.attendances.show'))
                                {{-- その日の勤怠レコードがあるとき --}}
                                <a class="ts-link" href="{{ route('admin.attendances.show', $attendanceId) }}">詳細</a>
                                @else
                                {{-- レコードがなくても空の勤怠詳細を開ける --}}
                                <a class="ts-link"
                                    href="{{ route('admin.attendances.showByUserAndDate', ['user' => $user->id, 'date' => $dateForUrl]) }}">
                                    詳細
                                </a>
                                @endif
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

        {{-- CSV出力 --}}
        @if (Route::has('admin.staff.attendances.export'))
        <div class="satt-export-wrap">
            <a class="satt-export-btn"
                href="{{ route('admin.staff.attendances.export', [$user->id, 'month' => $ym->format('Y-m')]) }}">
                CSV出力
            </a>
        </div>
        @endif

    </div>
</div>
@endsection