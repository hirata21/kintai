<?php

namespace App\Http\Controllers;

use App\Models\TimesheetRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MyRequestController extends Controller
{
    /**
     * 自分の申請一覧（タブ: pending / approved）
     */
    public function index(Request $request)
    {
        // ----------------------------
        // 認証チェック
        // ----------------------------
        $userId = Auth::id();
        if (!$userId) {
            return redirect()->route('login');
        }

        // ----------------------------
        // 入力: month(YYYY-MM) / status
        // ----------------------------
        $ymStr  = (string) $request->query('month', '');
        $status = (string) $request->query('status', 'pending');

        // statusのバリデーション（pending/approved 以外は pending に寄せる）
        if (!in_array($status, ['pending', 'approved'], true)) {
            $status = 'pending';
        }

        // ----------------------------
        // 月の起点を決定（不正値は今月にフォールバック）
        // 期間は [月初00:00:00, 月末23:59:59] に設定
        // ----------------------------
        try {
            $ym = $ymStr
                ? Carbon::createFromFormat('Y-m', $ymStr)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $ym = now()->startOfMonth();
        }

        $periodStart = $ym->copy()->startOfMonth()->startOfDay();
        $periodEnd   = $ym->copy()->endOfMonth()->endOfDay();

        // ページャに付与する月の文字列（YYYY-MM）
        $ymForPager = $ym->format('Y-m');

        // ----------------------------
        // クエリ本体
        // - requester_id は使用しない
        // - 自分の申請(user_id=自分)のみ
        // - N+1回避のため attendance・user を事前ロード
        // ----------------------------
        $baseQuery = TimesheetRequest::query()
            ->with(['attendance', 'user'])
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        // タブの状態で絞り込み
        $rows = (clone $baseQuery)
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->appends([
                'month'  => $ymForPager,
                'status' => $status,
            ]);

        // ----------------------------
        // ビューへ
        // - ビューは month切替に prevYm / nextYm を使う前提
        // - ym はCarbonで渡しておく（ヘッダ表示などで便利）
        // ----------------------------
        return view('requests.index', [
            'ym'     => $ym->copy(), // Carbon
            'prevYm' => $ym->copy()->subMonth()->format('Y-m'),
            'nextYm' => $ym->copy()->addMonth()->format('Y-m'),
            'status' => $status,
            'rows'   => $rows,
        ]);
    }
}