@extends('layouts.admin')
@section('title', '管理：申請一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/requests/index.css') }}">
@endsection

@section('content')
@php
// Controller 側の想定:
// $tab : 'pending' | 'closed'（または 'approved' を 'closed' 同等で扱う）
// $rows : コレクション（TimesheetRequest の Eloquent 推奨）
\Carbon\Carbon::setLocale('ja');

$isPendingTab = $tab === 'pending';
$isClosedTab = ($tab === 'closed' || $tab === 'approved');

// タブのルートは既存前提
$pendingUrl = route('admin.requests.index', ['tab' => 'pending']);
$closedUrl = route('admin.requests.index', ['tab' => 'closed']);
@endphp

<div class="rq-page">
    <div class="rq-inner">
        {{-- 見出し --}}
        <div class="rq-heading rq-block">
            <h1 class="rq-heading__text">申請一覧</h1>
        </div>

        {{-- タブ（承認待ち / 承認済み） --}}
        <nav class="rq-tabs rq-block" role="tablist" aria-label="申請フィルター">
            <a
                id="rq-tab-pending"
                class="rq-tab {{ $isPendingTab ? 'is-active' : '' }}"
                role="tab"
                href="{{ $pendingUrl }}"
                aria-selected="{{ $isPendingTab ? 'true' : 'false' }}"
                aria-controls="rq-panel-table">
                承認待ち
            </a>

            <a
                id="rq-tab-closed"
                class="rq-tab {{ $isClosedTab ? 'is-active' : '' }}"
                role="tab"
                href="{{ $closedUrl }}"
                aria-selected="{{ $isClosedTab ? 'true' : 'false' }}"
                aria-controls="rq-panel-table">
                承認済み
            </a>
        </nav>

        {{-- タブ下の仕切り線 --}}
        <div class="rq-divider rq-block" aria-hidden="true"></div>

        {{-- テーブル本体 --}}
        <div id="rq-panel-table" class="rq-table-wrap" role="tabpanel" aria-labelledby="{{ $isPendingTab ? 'rq-tab-pending' : 'rq-tab-closed' }}">
            <table class="rq-table rq-block">
                <caption class="sr-only">申請の一覧（状態、名前、対象日時、申請理由、申請日時、詳細）</caption>
                <thead>
                    <tr>
                        <th scope="col" class="rq-th-status">状態</th>
                        <th scope="col">名前</th>
                        <th scope="col">対象日時</th>
                        <th scope="col">申請理由</th>
                        <th scope="col">申請日時</th>
                        <th scope="col" class="rq-th-detail">詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                    @php
                    // 対象日/申請日の表示
                    $targetDate = optional($r->attendance?->work_date)->format('Y/m/d') ?? '—';
                    $appliedAt = optional($r->created_at)->format('Y/m/d') ?? '—';

                    // 状態ラベル
                    $statusLabelMap = ['pending' => '承認待ち', 'approved' => '承認済み', 'closed' => '承認済み'];
                    $statusLabel = $statusLabelMap[$r->status] ?? ($statusLabelMap[$tab] ?? '—');

                    /**
                    * 申請理由の取得（payload_current['note'] → requests.note → attendance.note）
                    * ※ モデルで $casts['payload_current' => 'array'] を付けていれば配列想定。
                    * QueryBuilder 由来などで文字列の可能性があるため、ここで念のため正規化。
                    */
                    $payload = $r->payload_current ?? [];
                    if (is_string($payload)) {
                    $decoded = json_decode($payload, true);
                    $payload = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
                    } elseif (is_object($payload)) {
                    $payload = (array) $payload;
                    } elseif (!is_array($payload)) {
                    $payload = [];
                    }

                    $reasonText = '';
                    if (isset($payload['note']) && is_string($payload['note'])) {
                    $reasonText = trim($payload['note']);
                    }
                    if ($reasonText === '') {
                    $reasonText = is_string($r->note ?? null) ? trim($r->note) : '';
                    }
                    if ($reasonText === '') {
                    $reasonText = is_string(optional($r->attendance)->note ?? null) ? trim(optional($r->attendance)->note) : '';
                    }

                    // 詳細URL（存在すればリンク、なければ無効表示）
                    $detailUrl = \Illuminate\Support\Facades\Route::has('admin.requests.approve.form')
                    ? route('admin.requests.approve.form', $r->id)
                    : null;
                    @endphp
                    <tr>
                        <td data-label="状態">{{ $statusLabel }}</td>
                        <td data-label="名前">{{ $r->requester->name ?? '—' }}</td>
                        <td data-label="対象日時">{{ $targetDate }}</td>
                        <td data-label="申請理由" class="rq-note">{{ $reasonText !== '' ? $reasonText : '—' }}</td>
                        <td data-label="申請日時">{{ $appliedAt }}</td>
                        <td class="rq-detail-cell" data-label="詳細">
                            @if ($detailUrl)
                            <a class="rq-detail-link" href="{{ $detailUrl }}" onclick="event.stopPropagation();">詳細</a>
                            @else
                            <span class="rq-detail-link rq-detail-link--disabled" aria-disabled="true">詳細</span>
                            @endif
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

    </div>
</div>
@endsection