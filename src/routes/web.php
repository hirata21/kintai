<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

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

    // Fortify のログイン処理（一般ユーザー・管理者共通の実処理）
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');

    // 管理者ログイン処理（名前だけ分けておく）
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.admin');

    // 会員登録ページ表示
    Route::view('/register', 'auth.register')
        ->name('register');

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
Route::get(
    '/verify/open-mail',
    fn() => redirect()->away(config('services.mailhog.url', 'http://localhost:8025'))
)->middleware('auth')
    ->name('verify.mailhog');


/*
|--------------------------------------------------------------------------
| Application（認証が必要なエリア：一般ユーザー）
|--------------------------------------------------------------------------
| - 打刻、勤怠一覧、申請作成など。
| - ログインしていないと入れない。
*/

Route::middleware('auth')->group(function () {

    /* ===== 打刻機能 ===== */

    // 勤怠登録画面（出勤／退勤ボタン表示）
    Route::get('/attendance', [AttendanceController::class, 'show'])
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
    Route::get('/attendance/list', [TimesheetController::class, 'index'])
        ->name('timesheet.index');

    // 日付指定で勤怠詳細を見る（例: /timesheet/2025-10-25）
    // ※ これは仕様外の追加機能（任意）
    Route::get('/timesheet/{date}', [TimesheetController::class, 'showByDate'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('timesheet.showByDate');

    // レコードIDで勤怠詳細を見る
    Route::get('/attendance/detail/{attendance}', [TimesheetController::class, 'show'])
        ->name('timesheet.show');

    // 勤怠修正などの申請を作成する（POST）
    Route::post('/requests', [TimesheetController::class, 'store'])
        ->name('requests.store');

    /* ===== 申請一覧（一般 + 管理者 共通URL） =====
       一般ユーザーと管理者で同じURIを使う仕様なので、
       ここでログインユーザーの権限を見て処理を出し分ける。
       - 一般ユーザー: MyRequestController@index
       - 管理者      : AdminRequest@index
    */
    Route::get('/stamp_correction_request/list', function (Request $request) {

        $user = $request->user();

        if ($user && $user->can('admin')) {
            // 管理者用 申請一覧
            return app(AdminRequest::class)->index($request);
        }

        // 一般ユーザー用 申請一覧
        return app(MyRequestController::class)->index($request);
    })->name('requests.index');
});


/*
|--------------------------------------------------------------------------
| Admin（管理者専用：/admin 配下）
|--------------------------------------------------------------------------
| - ログイン + 管理者権限（can:admin）が必要。
| - 勤怠管理、スタッフ管理など。
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:admin'])->group(function () {

    /* ===== 勤怠（管理側） ===== */

    // ユーザー & 日付で画面を開く（レコードがなくても開ける）
    // （仕様外だが便利機能として残す）
    Route::get('/attendances/user/{user}/{date}', [AdminAttendance::class, 'showByUserAndDate'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendances.showByUserAndDate');

    // 修正ボタンのPOST先（新規作成 or 更新）
    Route::post('/attendances/user/{user}/{date}', [AdminAttendance::class, 'store'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendances.storeByUserAndDate');

    // 勤怠一覧
    Route::get('/attendance/list', [AdminAttendance::class, 'index'])
        ->name('attendances.index');

    // 旧URL（/admin/attendances）からの互換リダイレクト（任意）
    Route::get('/attendances', fn() => redirect()->route('admin.attendances.index'))
        ->name('attendances.legacy');

    // レコードIDでの勤怠詳細
    Route::get('/attendance/{attendance}', [AdminAttendance::class, 'show'])
        ->whereNumber('attendance')
        ->name('attendances.show');

    // 既存勤怠の更新
    Route::patch('/attendance/{attendance}', [AdminAttendance::class, 'update'])
        ->whereNumber('attendance')
        ->name('attendances.update');


    /* ===== スタッフ管理 ===== */

    // スタッフ一覧
    Route::get('/staff/list', [AdminStaff::class, 'index'])
        ->name('staff.index');

    // スタッフ別勤怠一覧
    Route::get('/attendance/staff/{user}', [AdminStaff::class, 'attendances'])
        ->name('staff.attendances');

    // スタッフ別勤怠のエクスポート
    Route::get('/attendance/staff/{user}/export', [AdminStaff::class, 'export'])
        ->name('staff.attendances.export');
});


/*
|--------------------------------------------------------------------------
| 申請承認（管理者専用：/admin なしでアクセス）
|--------------------------------------------------------------------------
| - URL は /admin を付けないが、auth + can:admin で管理者専用にする
*/

Route::middleware(['auth', 'can:admin'])->group(function () {

    // 承認画面（確認フォーム）
    Route::get('/stamp_correction_request/approve/{id}', [AdminRequest::class, 'approveForm'])
        ->whereNumber('id')
        ->name('requests.approve.form');

    // 承認実行（POST）
    Route::post('/stamp_correction_request/approve/{id}', [AdminRequest::class, 'approve'])
        ->whereNumber('id')
        ->name('requests.approve');
});