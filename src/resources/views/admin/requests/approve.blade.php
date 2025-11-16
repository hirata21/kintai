@extends('layouts.admin')
@section('title', '管理：勤怠詳細（承認）')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/requests/approve.css') }}">
@endsection

@section('content')
@php
/**
* @var \App\Models\TimesheetRequest $correction
*/
\Carbon\Carbon::setLocale('ja');

$att = $correction->attendance;

/**
* payload_current を配列に正規化
* - Eloquentの $casts で array になっていても、念のためここで再度安全化
* - 文字列(JSON) / オブジェクト / null すべてに対応
*/
$pcRaw = $correction->payload_current ?? [];
if (is_string($pcRaw)) {
$decoded = json_decode($pcRaw, true);
$after = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
} elseif (is_array($pcRaw)) {
$after = $pcRaw;
} elseif (is_object($pcRaw)) {
$after = (array) $pcRaw;
} else {
$after = [];
}

/** 時刻を "H:i" にそろえる */
$fmtTime = function ($val, $fallback = '') {
if ($val === null || $val === '') return $fallback;
if (is_string($val) && preg_match('/^\d{1,2}:\d{2}$/', $val)) {
[$h, $m] = explode(':', $val);
return sprintf('%02d:%02d', $h, $m);
}
try {
return \Carbon\Carbon::parse($val)->format('H:i');
} catch (\Throwable $e) {
return $fallback;
}
};

/**
* 出勤・退勤：申請（after=payload_current）優先 → 元レコード
* キー互換: start_at/clock_in_at, end_at/clock_out_at
*/
$afterIn = $after['start_at'] ?? $after['clock_in_at'] ?? null;
$afterOut = $after['end_at'] ?? $after['clock_out_at'] ?? null;

$beforeIn = $att?->start_at ?? $att?->clock_in_at ?? null;
$beforeOut = $att?->end_at ?? $att?->clock_out_at ?? null;

$clockInText = $fmtTime($afterIn ?? $beforeIn, '');
$clockOutText = $fmtTime($afterOut ?? $beforeOut, '');

/** 氏名・所属・日付 */
$userName = $att?->user?->name ?? $correction->requester?->name ?? '';
$deptName = optional($att?->user?->department)->name ?? '';
$workDateY = $att?->work_date?->format('Y年') ?? '';
$workDateD = $att?->work_date?->translatedFormat('n月j日') ?? '';

/**
* 休憩：payload_current.breaks 優先（配列 or JSON文字列想定）
* rowのキー互換: start/start_at, end/end_at
* 空なら元レコードの breaks を使用。最大2行表示。
*/
$afterBreaks = [];
if (!empty($after['breaks'])) {
$tmpBreaks = is_string($after['breaks']) ? json_decode($after['breaks'], true) : $after['breaks'];
if (is_array($tmpBreaks)) {
foreach ($tmpBreaks as $row) {
$afterBreaks[] = [
'start' => $row['start'] ?? $row['start_at'] ?? null,
'end' => $row['end'] ?? $row['end_at'] ?? null,
];
}
}
}
if (empty($afterBreaks)) {
$afterBreaks = ($att?->breaks?->sortBy('start_at')->map(fn($b) => [
'start' => optional($b->start_at)->format('H:i') ?? null,
'end' => optional($b->end_at)->format('H:i') ?? null,
])->values()->toArray()) ?? [];
}

$br1s = $fmtTime($afterBreaks[0]['start'] ?? null, '');
$br1e = $fmtTime($afterBreaks[0]['end'] ?? null, '');
$br2s = $fmtTime($afterBreaks[1]['start'] ?? null, '');
$br2e = $fmtTime($afterBreaks[1]['end'] ?? null, '');

/** 備考：payload_current['note'] 優先 → 元レコード note */
$afterNote = isset($after['note']) ? (is_string($after['note']) ? trim($after['note']) : '') : '';
$note = $afterNote !== '' ? $afterNote : (is_string($att?->note) ? $att->note : '');

$isApproved = ($correction->status === 'approved');
@endphp

<main class="ts-approve-page" role="main">
    <h1 class="ts-heading">勤怠詳細</h1>

    <section class="ts-card" aria-labelledby="ts-card-title">
        {{-- 氏名 --}}
        <div class="ts-row">
            <div class="ts-label" id="lbl-name">氏名</div>
            <div class="ts-value ts-value--shift" aria-labelledby="lbl-name">
                <span class="ts-maintext">{{ $userName }}</span>
                @if ($deptName)
                <span class="ts-chip" aria-label="部署">{{ $deptName }}</span>
                @endif
            </div>
        </div>

        {{-- 日付 --}}
        <div class="ts-row">
            <div class="ts-label" id="lbl-date">日付</div>
            <div class="ts-value ts-value--shift ts-value--split" aria-labelledby="lbl-date">
                <span class="ts-maintext">{{ $workDateY }}</span>
                <span class="ts-maintext">{{ $workDateD }}</span>
            </div>
        </div>

        {{-- 出勤・退勤 --}}
        <div class="ts-row">
            <div class="ts-label" id="lbl-inout">出勤・退勤</div>
            <div class="ts-value ts-range-grid" aria-labelledby="lbl-inout">
                @if ($clockInText !== '' || $clockOutText !== '')
                <div class="ts-range-start"><span class="ts-timebox">{{ $clockInText }}</span></div>
                <span class="ts-sep" aria-hidden="true">〜</span>
                <div class="ts-range-end"><span class="ts-timebox">{{ $clockOutText }}</span></div>
                @endif
            </div>
        </div>

        {{-- 休憩 --}}
        <div class="ts-row">
            <div class="ts-label" id="lbl-break1">休憩</div>
            <div class="ts-value ts-range-grid" aria-labelledby="lbl-break1">
                @if ($br1s !== '' || $br1e !== '')
                <div class="ts-range-start"><span class="ts-timebox">{{ $br1s }}</span></div>
                <span class="ts-sep" aria-hidden="true">〜</span>
                <div class="ts-range-end"><span class="ts-timebox">{{ $br1e }}</span></div>
                @endif
            </div>
        </div>

        {{-- 休憩2 --}}
        <div class="ts-row">
            <div class="ts-label" id="lbl-break2">休憩2</div>
            <div class="ts-value ts-range-grid" aria-labelledby="lbl-break2">
                @if ($br2s !== '' || $br2e !== '')
                <div class="ts-range-start"><span class="ts-timebox">{{ $br2s }}</span></div>
                <span class="ts-sep" aria-hidden="true">〜</span>
                <div class="ts-range-end"><span class="ts-timebox">{{ $br2e }}</span></div>
                @endif
            </div>
        </div>

        {{-- 備考 --}}
        <div class="ts-row ts-row--last">
            <div class="ts-label" id="lbl-note">備考</div>
            <div class="ts-value ts-value--note" aria-labelledby="lbl-note">
                @if ($note !== '')
                <div class="ts-notebox">{{ $note }}</div>
                @else
                <div class="ts-notebox ts-notebox--empty" aria-hidden="true"></div>
                @endif
            </div>
        </div>
    </section>

    {{-- 承認ボタン：data-ajax が true のときだけ非同期 --}}
    <form id="approveForm"
        method="POST"
        action="{{ route('admin.requests.approve', $correction->id) }}"
        class="ts-actions"
        data-ajax="true">
        @csrf
        <button id="approveBtn"
            type="submit"
            class="ts-approve-btn {{ $isApproved ? 'is-approved' : '' }}"
            data-initial-status="{{ $correction->status }}"
            @if($isApproved) disabled aria-disabled="true" @endif
            aria-label="この申請を承認">
            {{ $isApproved ? '承認済み' : '承認' }}
        </button>
    </form>
</main>

<script>
    (function() {
        const form = document.getElementById('approveForm');
        const btn = document.getElementById('approveBtn');
        if (!form || !btn) return;

        // 初期状態：承認済みならボタンを固定
        const initial = btn.dataset.initialStatus;
        if (initial === 'approved') {
            btn.textContent = '承認済み';
            btn.classList.add('is-approved');
            btn.disabled = true;
            btn.setAttribute('aria-disabled', 'true');
        }

        if (form.dataset.ajax !== 'true') return; // 通常submitにフォールバック

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (btn.disabled) return;

            const originalText = btn.textContent;
            btn.disabled = true;
            btn.setAttribute('aria-disabled', 'true');

            try {
                const action = form.getAttribute('action');
                const token = form.querySelector('input[name="_token"]')?.value;

                const res = await fetch(action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: new URLSearchParams({
                        _token: token
                    })
                });

                // content-typeを見てJSONが来たら読む
                let data = {};
                const ct = res.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    try {
                        data = await res.json();
                    } catch (_) {}
                }

                if (res.ok && (data?.ok === true || data?.status === 'ok' || ct && !ct.includes('application/json'))) {
                    // 成功時：「承認済み」に固定
                    btn.textContent = '承認済み';
                    btn.classList.add('is-approved');
                    btn.disabled = true;
                    btn.setAttribute('aria-disabled', 'true');
                } else {
                    alert(data?.message || '承認に失敗しました。');
                    btn.disabled = false;
                    btn.removeAttribute('aria-disabled');
                    btn.textContent = originalText;
                }
            } catch (err) {
                alert('ネットワークエラーが発生しました。');
                btn.disabled = false;
                btn.removeAttribute('aria-disabled');
                btn.textContent = originalText;
            }
        });
    })();
</script>
@endsection