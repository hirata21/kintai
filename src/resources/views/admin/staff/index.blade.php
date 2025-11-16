@extends('layouts.admin')
@section('title', '管理：スタッフ一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/staff/index.css') }}">
@endsection

@section('content')
<div class="stf-page">
    {{-- 見出し --}}
    <div class="stf-heading">
        <h1 class="stf-heading__text">スタッフ一覧</h1>
    </div>

    {{-- テーブル枠 --}}
    <div class="stf-table-card">
        <table class="stf-table">
            <caption class="sr-only">スタッフの一覧（名前、メールアドレス、月次勤怠の詳細リンク）</caption>
            <thead>
                <tr>
                    <th scope="col" class="stf-th-name">名前</th>
                    <th scope="col" class="stf-th-email">メールアドレス</th>
                    <th scope="col" class="stf-th-monthly">月次勤怠</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $u)
                <tr>
                    <td class="stf-td stf-td-name" data-label="名前">{{ $u->name }}</td>
                    <td class="stf-td stf-td-email" data-label="メールアドレス">{{ $u->email }}</td>
                    <td class="stf-td stf-td-detail" data-label="月次勤怠">
                        @if(Route::has('admin.staff.attendances'))
                        <a class="stf-detail-link" href="{{ route('admin.staff.attendances', $u->id) }}">詳細</a>
                        @else
                        <span class="stf-detail-link stf-detail-link--disabled" aria-disabled="true">詳細</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="stf-empty">スタッフが見つかりません</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection