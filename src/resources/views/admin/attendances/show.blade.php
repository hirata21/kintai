@extends('layouts.admin')
@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/show.css') }}">
@endsection

@section('content')
@php
\Carbon\Carbon::setLocale('ja');

// Controller から渡る前提:
// - $attendance (\App\Models\Attendance, relations: user, breaks[orderBy start_at])
// - $isPendingApproval (bool)
// - $isExisting (bool)
$pending = (bool)($isPendingApproval ?? false);
$userName = optional($attendance->user)->name ?? '—';
$yearText = $attendance->work_date?->format('Y年') ?? '—';
$monthDay = $attendance->work_date?->translatedFormat('n月j日') ?? '—';
$startText = optional($attendance->start_at)->format('H:i') ?? '';
$endText = optional($attendance->end_at)->format('H:i') ?? '';
$note = old('note', $attendance->note ?? '');

// 休憩行（既存 + 空行1つ）
$existingBreaks = $attendance->relationLoaded('breaks')
? $attendance->breaks->sortBy('start_at')->map(fn($b) => [
'start' => optional($b->start_at)->format('H:i') ?? '',
'end' => optional($b->end_at)->format('H:i') ?? '',
])->values()->toArray()
: [];
$breakRows = old('breaks') ?? array_merge($existingBreaks, [['start' => '', 'end' => '']]);

// work_date を安全に YYYY-MM-DD へ（Carbon/文字列どちらでもOK）
$workDateStr = \Illuminate\Support\Carbon::parse($attendance->work_date)->toDateString();
@endphp

<main class="timesheet-detail {{ $pending ? 'is-pending' : '' }}" role="main">
    <h1 class="page-title">勤怠詳細</h1>

    <section class="detail-card">
        <form
            id="correction-form"
            method="POST"
            @if ($isExisting)
            action="{{ route('admin.attendances.update', ['attendance' => $attendance->id]) }}"
            @else
            action="{{ route('admin.attendances.showByUserAndDate', ['user' => $attendance->user_id, 'date' => $workDateStr]) }}"
            @endif
            class="correction-form"
            novalidate>
            @csrf
            @if ($isExisting)
            @method('PATCH')
            @else
            {{-- store() が参照するので hidden で送る --}}
            <input type="hidden" name="user_id" value="{{ $attendance->user_id }}">
            <input type="hidden" name="work_date" value="{{ $workDateStr }}">
            @endif

            {{-- 名前 --}}
            <div class="detail-row">
                <div class="label" id="lbl-name">名前</div>
                <div class="value" aria-labelledby="lbl-name">
                    <span class="value-text">{{ $userName }}</span>
                </div>
            </div>

            {{-- 日付 --}}
            <div class="detail-row">
                <div class="label" id="lbl-date">日付</div>
                <div class="value value--split" aria-labelledby="lbl-date">
                    <span class="value-text">{{ $yearText }}</span>
                    <span class="value-text">{{ $monthDay }}</span>
                </div>
            </div>

            {{-- 出勤・退勤 --}}
            <div class="detail-row">
                <div class="label" id="lbl-start-end">出勤・退勤</div>
                <div class="value time-range-value" aria-labelledby="lbl-start-end">
                    <div class="time-range-start">
                        <span class="readonly-time-label">{{ old('start_at', $startText) }}</span>
                        <input
                            type="text"
                            name="start_at"
                            class="time-input"
                            value="{{ old('start_at', $startText) }}"
                            inputmode="numeric"
                            maxlength="5"
                            autocomplete="off"
                            aria-label="出勤時刻（HH:MM）"
                            pattern="^\d{2}:\d{2}$"
                            title="時刻は HH:MM 形式で入力してください"
                            {{ $pending ? 'readonly' : '' }}>
                    </div>

                    <span class="sep" aria-hidden="true">〜</span>

                    <div class="time-range-end">
                        <span class="readonly-time-label">{{ old('end_at', $endText) }}</span>
                        <input
                            type="text"
                            name="end_at"
                            class="time-input"
                            value="{{ old('end_at', $endText) }}"
                            inputmode="numeric"
                            maxlength="5"
                            autocomplete="off"
                            aria-label="退勤時刻（HH:MM）"
                            pattern="^\d{2}:\d{2}$"
                            title="時刻は HH:MM 形式で入力してください"
                            {{ $pending ? 'readonly' : '' }}>
                    </div>
                </div>
            </div>

            {{-- 出勤・退勤のエラー --}}
            @if ($errors->has('start_at') || $errors->has('end_at'))
            <div class="detail-row" aria-live="polite">
                <div class="label" aria-hidden="true"></div>
                <div class="value">
                    <p class="error-msg">
                        {{ $errors->first('start_at') ?: $errors->first('end_at') }}
                    </p>
                </div>
            </div>
            @endif

            {{-- 休憩 多段 --}}
            @foreach ($breakRows as $i => $br)
            <div class="detail-row">
                <div class="label" id="lbl-break-{{ $i }}">{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</div>
                <div class="value time-range-value" aria-labelledby="lbl-break-{{ $i }}">
                    <div class="time-range-start">
                        <span class="readonly-time-label">{{ $br['start'] ?? '' }}</span>
                        <input
                            type="text"
                            name="breaks[{{ $i }}][start]"
                            class="time-input"
                            value="{{ old("breaks.$i.start", $br['start'] ?? '') }}"
                            inputmode="numeric"
                            maxlength="5"
                            autocomplete="off"
                            aria-label="休憩開始（HH:MM）"
                            pattern="^\d{2}:\d{2}$"
                            title="時刻は HH:MM 形式で入力してください"
                            {{ $pending ? 'readonly' : '' }}>
                    </div>

                    <span class="sep" aria-hidden="true">〜</span>

                    <div class="time-range-end">
                        <span class="readonly-time-label">{{ $br['end'] ?? '' }}</span>
                        <input
                            type="text"
                            name="breaks[{{ $i }}][end]"
                            class="time-input"
                            value="{{ old("breaks.$i.end", $br['end'] ?? '') }}"
                            inputmode="numeric"
                            maxlength="5"
                            autocomplete="off"
                            aria-label="休憩終了（HH:MM）"
                            pattern="^\d{2}:\d{2}$"
                            title="時刻は HH:MM 形式で入力してください"
                            {{ $pending ? 'readonly' : '' }}>
                    </div>
                </div>
            </div>

            @php
            $errs = array_values(array_filter([
            $errors->first("breaks.$i.start"),
            $errors->first("breaks.$i.end"),
            ]));
            @endphp
            @if (!empty($errs))
            <div class="detail-row" aria-live="polite">
                <div class="label" aria-hidden="true"></div>
                <div class="value">
                    <p class="error-msg">{{ implode(' ／ ', $errs) }}</p>
                </div>
            </div>
            @endif
            @endforeach

            {{-- 備考 --}}
            <div class="detail-row detail-row--last">
                <div class="label" id="lbl-note">備考</div>
                <div class="value value--full" aria-labelledby="lbl-note">
                    <textarea
                        name="note"
                        class="note-input"
                        rows="3"
                        aria-label="備考"
                        {{ $pending ? 'readonly' : '' }}>{{ $note }}</textarea>
                    @if ($errors->has('note'))
                    <p class="error-msg">{{ $errors->first('note') }}</p>
                    @endif
                </div>
            </div>
        </form>
    </section>

    <div class="actions-outside">
        @if ($pending)
        <p class="pending-text">*承認待ちのため修正はできません。</p>
        @else
        <button type="submit" form="correction-form" class="btn-submit">修正</button>
        @endif
    </div>
</main>

@if (! $pending)
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
                const pos = e.target.value.length;
                e.target.setSelectionRange(pos, pos);
            });
            el.addEventListener('blur', e => {
                const m = /^(\d{1,2}):(\d{1,2})$/.exec(e.target.value);
                if (m) e.target.value = clamp(m[1], m[2]);
            });
        });
    })();
</script>
@endif
@endsection