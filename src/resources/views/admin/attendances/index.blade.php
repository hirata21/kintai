@extends('layouts.admin')
@section('title', '勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/index.css') }}">
@endsection

@section('content')
@php
\Carbon\Carbon::setLocale('ja');
/** @var \Carbon\Carbon $date */
$titleDate = $date->translatedFormat('Y年n月j日');
$barDate = $date->format('Y/m/d');
$isoDate = $date->toDateString(); // 例: 2025-10-30
@endphp

<div class="at-page">
    <div class="at-inner">

        <div class="at-heading">
            <h1 class="at-heading__text">{{ $titleDate }}の勤怠</h1>
        </div>

        {{-- 日付切替バー --}}
        <nav class="at-navbar" aria-label="日付切替">
            <a class="at-nav at-nav--prev" href="{{ route('admin.attendances.index', ['date' => $prev]) }}">
                <img src="{{ asset('images/arrow.png') }}" alt="" aria-hidden="true" class="at-nav__icon at-nav__icon--left">
                <span class="at-nav__label">前日</span>
            </a>

            <div class="at-navbar__center" aria-live="polite">
                <img src="{{ asset('images/calendar.png') }}" alt="" aria-hidden="true" class="at-icon-calendar">
                <span class="at-date">{{ $barDate }}</span>
            </div>

            <a class="at-nav at-nav--next" href="{{ route('admin.attendances.index', ['date' => $next]) }}">
                <span class="at-nav__label">翌日</span>
                <img src="{{ asset('images/arrow.png') }}" alt="" aria-hidden="true" class="at-nav__icon at-nav__icon--right">
            </a>
        </nav>

        {{-- 勤怠テーブル --}}
        <div class="at-tablewrap">
            <table class="at-table">
                <caption class="sr-only">社員ごとの出勤・退勤・休憩・合計時間一覧</caption>
                <thead>
                    <tr>
                        <th scope="col">名前</th>
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
                    $userId = $r->user_id ?? null; // ← index() 側で必ず入れる
                    $userName = $r->user_name ?? '—';
                    $inDisplay = $r->in ?? '';
                    $outDisplay = $r->out ?? '';
                    $breakMin = (int)($r->break ?? 0);
                    $workMin = (int)($r->work ?? 0);
                    $attendanceId = $r->attendance_id ?? null;

                    $breakDisplay = $breakMin ? sprintf('%d:%02d', intdiv($breakMin, 60), $breakMin % 60) : '';
                    $workDisplay = $workMin ? sprintf('%d:%02d', intdiv($workMin, 60), $workMin % 60) : '';
                    @endphp

                    <tr>
                        <td class="at-col-name">{{ $userName }}</td>
                        <td class="at-col-time">{{ $inDisplay }}</td>
                        <td class="at-col-time">{{ $outDisplay }}</td>
                        <td class="at-col-time">{{ $breakDisplay }}</td>
                        <td class="at-col-time">{{ $workDisplay }}</td>
                        <td class="at-col-detail">
                            @if ($attendanceId)
                            {{-- 既存レコードあり：IDで通常の詳細へ --}}
                            <a class="at-link" href="{{ route('admin.attendances.show', $attendanceId) }}">詳細</a>
                            @elseif ($userId)
                            {{-- レコード無し：ユーザー×日付の詳細へ（新規作成可能画面） --}}
                            <a class="at-link"
                                href="{{ route('admin.attendances.showByUserAndDate', ['user' => $userId, 'date' => $isoDate]) }}">
                                詳細
                            </a>
                            @else
                            {{-- どうしても user_id が無い場合のみ無効表示（想定外） --}}
                            <span class="at-link at-link--disabled" aria-disabled="true">詳細</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="at-empty">この日の勤怠はありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
@endsection