<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\MyRequestController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendance;
use App\Http\Controllers\Admin\StaffController as AdminStaff;
use App\Http\Controllers\Admin\RequestController as AdminRequest;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Public（ログイン不要でアクセスできる領域）
|--------------------------------------------------------------------------
| - 会員登録、ログイン画面など、未ログイン（ゲスト）向けのルート。
| - 既にログインしているユーザーが再度アクセスしないよう、
|   guest ミドルウェアで保護しています。
*/

Route::middleware('guest')->group(function () {

    // ユーザー向けログインページ表示
    Route::get('/login', [RegisteredUserController::class, 'showLogin'])
        ->name('login.show');

    // 管理者ログインページ表示
    Route::get('/admin/login', fn() => view('admin.auth.login'))
        ->name('admin.login');

    // Fortify のログイン処理（POST）
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');

    // 管理者ログイン処理（POST） ※名前は区別しておく
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.admin');

    // 会員登録ページ表示
    Route::view('/register', 'auth.register')->name('register');

    // 会員登録処理
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->name('register.store');
});


/*
|--------------------------------------------------------------------------
| Email 認証（メール認証フロー）
|--------------------------------------------------------------------------
| - 初回登録後、メールのリンクをクリックして本人確認する流れ。
| - メール認証が必要なページへ進む前にここへリダイレクトされます。
*/

// 認証待ち案内ページ（ログイン必須）
Route::get('/email/verify', fn() => view('auth.verify-prompt'))
    ->middleware('auth')
    ->name('verification.notice');

// メール内リンク（署名付き URL）→ 認証完了処理
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill(); // email_verified_at に日時を保存
    return redirect()->route('punch.show'); // 認証後に遷移するページ
})->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

// MailHog を開くショートカット（開発用。認証済みユーザのみ）
Route::get('/verify/open-mail', fn() => redirect()->away(config('services.mailhog.url', 'http://localhost:8025')))
    ->middleware('auth')
    ->name('verify.mailhog');


/*
|--------------------------------------------------------------------------
| Application（認証が必要なエリア）
|--------------------------------------------------------------------------
| - 打刻、勤怠一覧、申請作成など。
| - ログインしていないと入れない。
*/
Route::middleware('auth')->group(function () {

    /* ===== 打刻機能 ===== */

    // 打刻画面（出勤／退勤ボタン表示）
    Route::get('/punch', [AttendanceController::class, 'show'])
        ->name('punch.show');

    // 出勤（打刻）
    Route::post('/punch/in', [AttendanceController::class, 'clockIn'])
        ->name('punch.in');

    // 退勤（打刻）
    Route::post('/punch/out', [AttendanceController::class, 'clockOut'])
        ->name('punch.out');

    // 休憩開始
    Route::post('/punch/break-in', [AttendanceController::class, 'breakIn'])
        ->name('punch.break.in');

    // 休憩終了
    Route::post('/punch/break-out', [AttendanceController::class, 'breakOut'])
        ->name('punch.break.out');


    /* ===== 勤怠（ユーザー側） ===== */

    // 勤怠一覧
    Route::get('/timesheet', [TimesheetController::class, 'index'])
        ->name('timesheet.index');

    // 日付指定で勤怠詳細を見る（例: /timesheet/2025-10-25）
    Route::get('/timesheet/{date}', [TimesheetController::class, 'showByDate'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('timesheet.showByDate');

    // レコードIDで勤怠詳細を見る
    Route::get('/timesheet/detail/{attendance}', [TimesheetController::class, 'show'])
        ->name('timesheet.show');

    // 勤怠修正などの申請を作成する（POST）
    Route::post('/requests', [TimesheetController::class, 'store'])
        ->name('requests.store');

    // 自分の申請一覧
    Route::get('/my/requests', [MyRequestController::class, 'index'])
        ->name('requests.index');
});


/*
|--------------------------------------------------------------------------
| Admin（管理者専用）
|--------------------------------------------------------------------------
| - ログイン + 管理者権限（can:admin）が必要。
| - 勤怠管理、申請承認、スタッフ管理など。
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:admin'])->group(function () {

    /* ===== 勤怠 ===== */

    // ユーザー & 日付で画面を開く（レコードがなくても開ける）
    Route::get('/attendances/user/{user}/{date}', [AdminAttendance::class, 'showByUserAndDate'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendances.showByUserAndDate');

    // 修正ボタンのPOST先（新規作成 or 更新）
    Route::post('/attendances/user/{user}/{date}', [AdminAttendance::class, 'store'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendances.storeByUserAndDate');

    // 全スタッフ分の勤怠一覧
    Route::get('/attendances', [AdminAttendance::class, 'index'])
        ->name('attendances.index');

    // レコードIDでの勤怠詳細（管理者閲覧用）
    Route::get('/attendances/{attendance}', [AdminAttendance::class, 'show'])
        ->whereNumber('attendance')
        ->name('attendances.show');

    // 既存勤怠の更新
    Route::patch('/attendances/{attendance}', [AdminAttendance::class, 'update'])
        ->whereNumber('attendance')
        ->name('attendances.update');

    // 古いURL対応（/admin/attendance/list → /admin/attendances）
    Route::get('/attendance/list', fn() => redirect()->route('admin.attendances.index'))
        ->name('attendance.list');


    /* ===== スタッフ管理 ===== */

    Route::get('/staffs', [AdminStaff::class, 'index'])
        ->name('staff.index');

    Route::get('/staffs/{user}/attendances', [AdminStaff::class, 'attendances'])
        ->name('staff.attendances');

    Route::get('/staffs/{user}/attendances/export', [AdminStaff::class, 'export'])
        ->name('staff.attendances.export');


    /* ===== 申請（承認フロー） ===== */

    // 申請一覧（pending / approved）
    Route::get('/requests', [AdminRequest::class, 'index'])
        ->name('requests.index');

    // 承認画面（確認フォーム）
    Route::get('/requests/{id}/approve', [AdminRequest::class, 'approveForm'])
        ->whereNumber('id')
        ->name('requests.approve.form');

    // 承認実行（POST）
    Route::post('/requests/{id}/approve', [AdminRequest::class, 'approve'])
        ->whereNumber('id')
        ->name('requests.approve');
});
