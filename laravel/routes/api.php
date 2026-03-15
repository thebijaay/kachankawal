<?php
// routes/api.php – Kachankawal Rural Municipality API
// All routes prefixed: /api/v1

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    UserController,
    WardController,
    RepresentativeController,
    NoticeController,
    ServiceRequestController,
    ComplaintController,
    EventController,
    FeedbackController,
    DashboardController,
    NotificationController,
};
use App\Http\Middleware\{JwtMiddleware, AdminMiddleware, WardAdminMiddleware};

// ──────────────────────────────────────────────
//  PUBLIC ROUTES (no auth required)
// ──────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('send-otp',      [AuthController::class, 'sendOtp']);
        Route::post('verify-otp',    [AuthController::class, 'verifyOtp']);
        Route::post('register',      [AuthController::class, 'register']);
        Route::post('login',         [AuthController::class, 'login']);         // password fallback
        Route::post('refresh',       [AuthController::class, 'refresh']);
    });

    // Public info (no login needed)
    Route::get('wards',                     [WardController::class, 'index']);
    Route::get('wards/{ward_no}',           [WardController::class, 'show']);
    Route::get('representatives',           [RepresentativeController::class, 'index']);
    Route::get('notices',                   [NoticeController::class, 'index']);
    Route::get('notices/{id}',              [NoticeController::class, 'show']);
    Route::get('events',                    [EventController::class, 'index']);
    Route::get('events/{id}',               [EventController::class, 'show']);

    // ──────────────────────────────────────────
    //  CITIZEN ROUTES (JWT required)
    // ──────────────────────────────────────────
    Route::middleware(JwtMiddleware::class)->group(function () {

        // Auth management
        Route::post('auth/logout',  [AuthController::class, 'logout']);
        Route::get('auth/me',       [AuthController::class, 'me']);

        // User profile
        Route::get('user/profile',          [UserController::class, 'profile']);
        Route::put('user/profile',          [UserController::class, 'updateProfile']);
        Route::post('user/update-photo',    [UserController::class, 'updatePhoto']);
        Route::post('user/device-token',    [UserController::class, 'updateDeviceToken']);

        // Service requests
        Route::prefix('services')->group(function () {
            Route::get('/',                 [ServiceRequestController::class, 'index']);
            Route::post('/',                [ServiceRequestController::class, 'store']);
            Route::get('/{tracking_no}',    [ServiceRequestController::class, 'show']);
            Route::post('/{id}/documents',  [ServiceRequestController::class, 'uploadDocument']);
        });

        // Complaints
        Route::prefix('complaints')->group(function () {
            Route::get('/',                 [ComplaintController::class, 'index']);
            Route::post('/',                [ComplaintController::class, 'store']);
            Route::get('/{tracking_no}',    [ComplaintController::class, 'show']);
            Route::post('/{id}/photos',     [ComplaintController::class, 'uploadPhoto']);
        });

        // Feedback
        Route::post('feedback',             [FeedbackController::class, 'store']);

        // Ward-specific notices
        Route::get('my-ward/notices',       [NoticeController::class, 'wardNotices']);
        Route::get('my-ward/events',        [EventController::class, 'wardEvents']);
    });

    // ──────────────────────────────────────────
    //  ADMIN ROUTES (JWT + admin role)
    // ──────────────────────────────────────────
    Route::middleware([JwtMiddleware::class, AdminMiddleware::class])->prefix('admin')->group(function () {

        // Dashboard
        Route::get('dashboard/stats',       [DashboardController::class, 'stats']);
        Route::get('dashboard/charts',      [DashboardController::class, 'charts']);

        // User management
        Route::get('users',                 [UserController::class, 'adminIndex']);
        Route::get('users/{id}',            [UserController::class, 'adminShow']);
        Route::put('users/{id}/status',     [UserController::class, 'toggleStatus']);

        // Content – Notices
        Route::apiResource('notices', NoticeController::class)->except(['index','show']);

        // Content – Events
        Route::apiResource('events', EventController::class)->except(['index','show']);

        // Representatives
        Route::apiResource('representatives', RepresentativeController::class);

        // Wards
        Route::put('wards/{ward_no}',       [WardController::class, 'update']);

        // Service requests
        Route::get('services',                      [ServiceRequestController::class, 'adminIndex']);
        Route::get('services/{id}',                 [ServiceRequestController::class, 'adminShow']);
        Route::put('services/{id}/status',          [ServiceRequestController::class, 'updateStatus']);
        Route::put('services/{id}/assign',          [ServiceRequestController::class, 'assign']);

        // Complaints
        Route::get('complaints',                    [ComplaintController::class, 'adminIndex']);
        Route::get('complaints/{id}',               [ComplaintController::class, 'adminShow']);
        Route::put('complaints/{id}/status',        [ComplaintController::class, 'updateStatus']);
        Route::put('complaints/{id}/assign',        [ComplaintController::class, 'assign']);

        // Push notifications
        Route::post('notifications/send',           [NotificationController::class, 'send']);
        Route::get('notifications/logs',            [NotificationController::class, 'logs']);

        // Feedback
        Route::get('feedback',                      [FeedbackController::class, 'index']);

        // Reports
        Route::get('reports/ward-activity',         [DashboardController::class, 'wardActivity']);
        Route::get('reports/service-summary',       [DashboardController::class, 'serviceSummary']);
    });
});
