@extends('layouts.app')
@section('title', '申請一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/requests/index.css') }}">
@endsection

@section('content')
@php
\Carbon\Carbon::setLocale('ja');
@endphp

<main class="rq-page" role="main">
    <section class="rq-inner" aria-labelledby="rq-heading">
        {{-- 見出し --}}
        <div class="rq-heading rq-block">
            <h1 id="rq-heading" class="rq-heading__text">申請一覧</h1>
        </div>

        {{-- タブ（承認待ち / 承認済み） --}}
        <nav class="rq-tabs rq-block" role="tablist" aria-label="申請の状態タブ">
            @php
            $isPending = $status === 'pending';
            $isApproved = $status === 'approved';
            @endphp

            <a
                role="tab"
                aria-selected="{{ $isPending ? 'true' : 'false' }}"
                aria-current="{{ $isPending ? 'page' : 'false' }}"
                class="rq-tab {{ $isPending ? 'is-active' : '' }}"
                href="{{ route('requests.index', ['month' => $ym->format('Y-m'), 'status' => 'pending']) }}">
                承認待ち
            </a>

            <a
                role="tab"
                aria-selected="{{ $isApproved ? 'true' : 'false' }}"
                aria-current="{{ $isApproved ? 'page' : 'false' }}"
                class="rq-tab {{ $isApproved ? 'is-active' : '' }}"
                href="{{ route('requests.index', ['month' => $ym->format('Y-m'), 'status' => 'approved']) }}">
                承認済み
            </a>
        </nav>

        {{-- タブ下の仕切り線 --}}
        <div class="rq-divider rq-block"></div>

        {{-- 申請テーブル --}}
        <div class="rq-table-wrap">
            <table class="rq-table rq-block">
                <caption class="sr-only">選択中のタブに応じた申請の一覧</caption>
                <thead>
                    <tr>
                        <th scope="col" class="rq-th-status">状態</th>
                        <th scope="col">名前</th>
                        <th scope="col">対象日</th>
                        <th scope="col">申請理由</th>
                        <th scope="col">申請日</th>
                        <th scope="col" class="rq-th-detail">詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $requestItem)
                    @php
                    // 日付は time 要素で
                    $workDate = optional($requestItem->attendance?->work_date);
                    $dateIso = $workDate?->format('Y-m-d');
                    $dateLabel = $workDate?->format('Y/m/d') ?? '—';

                    $applied = optional($requestItem->created_at);
                    $appliedIso = $applied?->format('Y-m-d');
                    $appliedAt = $applied?->format('Y/m/d') ?? '—';

                    // ステータス表示
                    $statusLabelMap = ['pending' => '承認待ち', 'approved' => '承認済み'];
                    $statusLabel = $statusLabelMap[$requestItem->status] ?? '—';

                    // payload_current（のみ）から note を取り出す
                    $payloadRaw = $requestItem->payload_current ?? null;
                    $payloadArr = is_array($payloadRaw)
                    ? $payloadRaw
                    : (is_string($payloadRaw) ? (json_decode($payloadRaw, true) ?: null) : null);

                    $reasonText =
                    ($payloadArr['note'] ?? null)
                    ?? $requestItem->note
                    ?? optional($requestItem->attendance)->note
                    ?? '—';
                    @endphp

                    <tr>
                        <td data-label="状態" class="rq-status-cell">
                            {{ $statusLabel }}
                        </td>
                        <td data-label="名前">
                            {{ $requestItem->user->name ?? '—' }}
                        </td>
                        <td data-label="対象日">
                            @if ($dateIso)
                            <time datetime="{{ $dateIso }}">{{ $dateLabel }}</time>
                            @else
                            —
                            @endif
                        </td>
                        <td data-label="申請理由" class="rq-note">
                            {{ $reasonText }}
                        </td>
                        <td data-label="申請日">
                            @if ($appliedIso)
                            <time datetime="{{ $appliedIso }}">{{ $appliedAt }}</time>
                            @else
                            —
                            @endif
                        </td>
                        <td class="rq-detail-cell" data-label="詳細">
                            <a
                                class="rq-detail-link"
                                href="{{ route('timesheet.show', [
                                        'attendance' => $requestItem->attendance?->id ?? $requestItem->attendance_id,
                                        'request_id' => $requestItem->id,
                                    ]) }}"
                                aria-label="詳細（{{ $dateLabel }} の申請）"
                                onclick="event.stopPropagation();">詳細</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="rq-empty">対象の申請はありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection