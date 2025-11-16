@extends('layouts.app')
@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/timesheet/show.css') }}">
@endsection

@section('content')
@php
/**
* @var \App\Models\Attendance $attendance
* @var bool $isPendingApproval
*/
\Carbon\Carbon::setLocale('ja');

$isPendingApproval = $isPendingApproval ?? false;
$isRequestView = request()->filled('request_id'); // ★ 申請経由の詳細表示か？

// 表示用テキスト
$userName = $attendance->user->name ?? auth()->user()->name ?? '—';
$yearText = $attendance->work_date?->format('Y年') ?? '—';
$monthDayText = $attendance->work_date?->translatedFormat('n月j日') ?? '—';

// start/end（start_at / end_at を採用）
$startText = optional($attendance->start_at)->format('H:i') ?? '';
$endText = optional($attendance->end_at)->format('H:i') ?? '';

// ===== 休憩：モデル→H:iへ整形（改良版） =====
$emptyRow = ['start_at' => '', 'end_at' => ''];

// 1) DBからの既存レコード（時刻は H:i 形式）。存在しなければ空の配列。
$existingBreaks = $attendance->breaks
    ->sortBy('start_at')
    ->map(function ($b) {
        return [
            'start_at' => optional($b->start_at)->format('H:i') ?? '',
            'end_at'   => optional($b->end_at)->format('H:i') ?? '',
        ];
    })
    ->values()
    ->toArray();

// 2) old() 優先で行リストを決める。old があるときはそれを尊重し、最後に必ず1行の空行を付ける。
if (!is_null(old('breaks'))) {
    // old('breaks') は連想配列の可能性があるので数値添字に揃える
    $oldRows = array_values(old('breaks'));

    // 正規化：各要素が配列でなければ空行に置き換える（堅牢性）
    $normalized = array_map(function ($r) {
        if (!is_array($r)) return ['start_at' => '', 'end_at' => ''];
        return [
            'start_at' => isset($r['start_at']) ? (string)$r['start_at'] : '',
            'end_at'   => isset($r['end_at']) ? (string)$r['end_at'] : '',
        ];
    }, $oldRows);

    // 最後の行が空でなければ空行を追加（常に追加用の入力フィールドを表示するため）
    $last = end($normalized);
    if (! ($last['start_at'] === '' && $last['end_at'] === '')) {
        $normalized[] = $emptyRow;
    }

    $breakRows = $normalized;
} else {
    // old が無ければ DBの既存レコード + 追加空行（既存0件でも空行は1つある）
    $breakRows = array_merge($existingBreaks ?: [], [$emptyRow]);
}

// 3) 申請経由（request_idあり）では「2行目以降で両方空」の行は非表示にする仕様
if ($isRequestView) {
    $breakRows = collect($breakRows)
        ->filter(function ($row, $idx) {
            if ($idx === 0) return true; // 1行目は常に見せる
            return (trim((string)$row['start_at']) !== '' || trim((string)$row['end_at']) !== '');
        })
        ->values()
        ->all();
}

$note = old('note', $attendance->note ?? '');
@endphp

<main class="timesheet-detail" role="main" aria-labelledby="tsd-heading">
    <h1 id="tsd-heading" class="page-title">勤怠詳細</h1>

    {{-- フォームはセクション全体（フィールド＋ボタン）を正しくラップ --}}
    <form method="POST" action="{{ route('requests.store') }}" class="correction-form" novalidate>
        @csrf
        <input type="hidden" name="attendance_id" value="{{ $attendance->id }}">

        <section class="detail-card" aria-label="勤怠情報の修正申請">
            {{-- 名前 --}}
            <div class="detail-row">
                <label class="label" for="tsd-name">名前</label>
                <div class="value">
                    <span id="tsd-name" class="value-text">{{ $userName }}</span>
                </div>
            </div>

            {{-- 日付 --}}
            <div class="detail-row">
                <span class="label" id="tsd-date-label">日付</span>
                <div class="value value--split" aria-labelledby="tsd-date-label">
                    <span class="value-text">{{ $yearText }}</span>
                    <span class="value-text">{{ $monthDayText }}</span>
                </div>
            </div>

            {{-- 出勤・退勤 --}}
            @php
            $startId = 'start_at';
            $endId = 'end_at';
            $errStart = $errors->first('start_at');
            $errEnd = $errors->first('end_at');
            $timeErrId = $errStart ? 'error-start_at' : ($errEnd ? 'error-end_at' : null);
            @endphp

            <div class="detail-row">
                <label class="label" for="{{ $startId }}">出勤・退勤</label>
                <div class="value time-range-value">
                    <div class="time-range-start">
                        <input
                            type="text"
                            id="{{ $startId }}"
                            name="start_at"
                            class="time-input"
                            value="{{ old('start_at', $startText) }}"
                            inputmode="numeric"
                            pattern="^\d{2}:\d{2}$"
                            maxlength="5"
                            autocomplete="off"
                            @if($timeErrId) aria-describedby="{{ $timeErrId }}" aria-invalid="true" @endif
                            {{ $isPendingApproval ? 'readonly' : '' }}>
                    </div>

                    <span class="sep" aria-hidden="true">〜</span>

                    <div class="time-range-end">
                        <input
                            type="text"
                            id="{{ $endId }}"
                            name="end_at"
                            class="time-input"
                            value="{{ old('end_at', $endText) }}"
                            inputmode="numeric"
                            pattern="^\d{2}:\d{2}$"
                            maxlength="5"
                            autocomplete="off"
                            @if($timeErrId) aria-describedby="{{ $timeErrId }}" aria-invalid="true" @endif
                            {{ $isPendingApproval ? 'readonly' : '' }}>
                    </div>
                </div>
            </div>

            @if ($errStart || $errEnd)
            <div class="detail-row">
                <div class="label"></div>
                <div class="value">
                    <p class="error" id="{{ $timeErrId }}" role="alert">
                        {{ $errStart ?: $errEnd }}
                    </p>
                </div>
            </div>
            @endif

            {{-- 休憩（複数行：申請経由は2行目以降の空行を非表示） --}}
            @foreach ($breakRows as $i => $br)
            @php
            $bidS = "breaks_{$i}_start_at";
            $bidE = "breaks_{$i}_end_at";
            $bErrS = $errors->first("breaks.$i.start_at");
            $bErrE = $errors->first("breaks.$i.end_at");
            $bErrId = $bErrS ? "error-breaks-{$i}-start" : ($bErrE ? "error-breaks-{$i}-end" : null);
            @endphp
            <div class="detail-row">
                <label class="label" for="{{ $bidS }}">{{ $i === 0 ? '休憩' : '休憩'.($i + 1) }}</label>

                <div class="value time-range-value">
                    <div class="time-range-start">
                        <input
                            type="text"
                            id="{{ $bidS }}"
                            name="breaks[{{ $i }}][start_at]"
                            class="time-input"
                            value="{{ old("breaks.$i.start_at", $br['start_at'] ?? $br['start'] ?? '') }}"
                            inputmode="numeric"
                            pattern="^\d{2}:\d{2}$"
                            maxlength="5"
                            autocomplete="off"
                            @if($bErrId) aria-describedby="{{ $bErrId }}" aria-invalid="true" @endif
                            {{ $isPendingApproval ? 'readonly' : '' }}>
                    </div>

                    <span class="sep" aria-hidden="true">〜</span>

                    <div class="time-range-end">
                        <input
                            type="text"
                            id="{{ $bidE }}"
                            name="breaks[{{ $i }}][end_at]"
                            class="time-input"
                            value="{{ old("breaks.$i.end_at", $br['end_at'] ?? $br['end'] ?? '') }}"
                            inputmode="numeric"
                            pattern="^\d{2}:\d{2}$"
                            maxlength="5"
                            autocomplete="off"
                            @if($bErrId) aria-describedby="{{ $bErrId }}" aria-invalid="true" @endif
                            {{ $isPendingApproval ? 'readonly' : '' }}>
                    </div>
                </div>
            </div>

            @if ($bErrS || $bErrE)
            <div class="detail-row">
                <div class="label"></div>
                <div class="value">
                    <p class="error" id="{{ $bErrId }}" role="alert">
                        {{ $bErrS ?: $bErrE }}
                    </p>
                </div>
            </div>
            @endif
            @endforeach

            {{-- 備考 --}}
            @php $noteErr = $errors->first('note'); @endphp
            <div class="detail-row">
                <label class="label" for="note">備考</label>
                <div class="value value--full">
                    <textarea
                        id="note"
                        name="note"
                        class="note-input"
                        rows="3"
                        @if($noteErr) aria-describedby="error-note" aria-invalid="true" @endif
                        {{ $isPendingApproval ? 'readonly' : 'required' }}>{{ $note }}</textarea>
                </div>
            </div>

            @error('note')
            <div class="detail-row">
                <div class="label"></div>
                <div class="value">
                    <p class="error" id="error-note" role="alert">{{ $message }}</p>
                </div>
            </div>
            @enderror
        </section>

        {{-- 最下部（修正 or 承認待ち） --}}
        <div class="actions">
            @if ($isPendingApproval)
            <p class="pending-text">*承認待ちのため修正はできません。</p>
            @else
            <button type="submit" class="btn-submit">修正</button>
            @endif
        </div>
    </form>
</main>

@if (! $isPendingApproval)
@push('scripts')
<script>
    (function() {
        function insertColon(v) {
            v = (v || '').replace(/[^\d]/g, '').slice(0, 4);
            if (v.length >= 3) return v.slice(0, 2) + ':' + v.slice(2);
            return v;
        }

        function clamp(h, m) {
            h = Math.min(Math.max(+h || 0, 0), 23);
            m = Math.min(Math.max(+m || 0, 0), 59);
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
        document.querySelectorAll('input.time-input').forEach(el => {
            el.addEventListener('input', e => {
                const formatted = insertColon(e.target.value);
                e.target.value = formatted;
                const endPos = e.target.value.length;
                e.target.setSelectionRange(endPos, endPos);
            });
            el.addEventListener('blur', e => {
                const m = /^(\d{1,2}):(\d{1,2})$/.exec(e.target.value);
                if (m) e.target.value = clamp(m[1], m[2]);
            });
        });
    })();
</script>
@endpush
@endif
@endsection