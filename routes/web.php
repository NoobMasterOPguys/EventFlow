<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\TaskController as AdminTaskController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'create'])->middleware('guest');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['auth', 'active.account', 'authenticated.cache'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('events', EventController::class);
    Route::post('/events/{event}/status', [EventController::class, 'updateStatus'])->name('events.status');
    Route::post('/events/{event}/members', [EventController::class, 'addMember'])->name('events.members.store');
    Route::delete('/events/{event}/members/{member}', [EventController::class, 'removeMember'])->name('events.members.destroy');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/events/{event}/tasks', [TaskController::class, 'store'])->name('events.tasks.store');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::get('/calendar', CalendarController::class)->name('calendar');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotificationPreferences'])->name('profile.notifications');
    Route::delete('/profile/account', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('admin')->name('admin.')->middleware('administrator')->group(function (): void {
        Route::get('/', AdminDashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])->middleware('can:users.view')->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->middleware('can:users.create')->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->middleware(['can:users.create', 'throttle:10,1'])->name('users.store');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->withTrashed()->middleware('can:users.view')->name('users.show');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->middleware('can:users.update')->name('users.update');
        Route::post('/users/{user}/password-reset', [AdminUserController::class, 'sendPasswordReset'])->middleware(['can:users.update', 'throttle:5,1'])->name('users.password-reset');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->middleware('can:users.change_role')->name('users.role');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->middleware('can:users.suspend')->name('users.status');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->middleware('can:users.delete')->name('users.destroy');
        Route::post('/users/{user}/restore', [AdminUserController::class, 'restore'])->withTrashed()->middleware('can:users.delete')->name('users.restore');

        Route::get('/events/create', [AdminEventController::class, 'create'])->middleware('can:events.create')->name('events.create');
        Route::post('/events', [AdminEventController::class, 'store'])->middleware('can:events.create')->name('events.store');
        Route::get('/events', [AdminEventController::class, 'index'])->middleware('can:events.view')->name('events.index');
        Route::get('/events/{event}', [AdminEventController::class, 'show'])->withTrashed()->middleware('can:events.view')->name('events.show');
        Route::patch('/events/{event}', [AdminEventController::class, 'update'])->middleware('can:events.update')->name('events.update');
        Route::patch('/events/{event}/status', [AdminEventController::class, 'updateStatus'])->middleware('can:events.update')->name('events.status');
        Route::post('/events/{event}/members', [AdminEventController::class, 'addMember'])->middleware('can:events.update')->name('events.members.store');
        Route::delete('/events/{event}/members/{member}', [AdminEventController::class, 'removeMember'])->middleware('can:events.update')->name('events.members.destroy');
        Route::delete('/events/{event}', [AdminEventController::class, 'destroy'])->middleware('can:events.delete')->name('events.destroy');
        Route::post('/events/{event}/restore', [AdminEventController::class, 'restore'])->withTrashed()->middleware('can:events.update')->name('events.restore');

        Route::get('/tasks', [AdminTaskController::class, 'index'])->middleware('can:tasks.view')->name('tasks.index');
        Route::get('/tasks/{task}/edit', [AdminTaskController::class, 'edit'])->middleware('can:tasks.view')->name('tasks.edit');
        Route::patch('/tasks/{task}', [AdminTaskController::class, 'update'])->middleware('can:tasks.manage')->name('tasks.update');
        Route::delete('/tasks/{task}', [AdminTaskController::class, 'destroy'])->middleware('can:tasks.manage')->name('tasks.destroy');
        Route::post('/tasks/{task}/restore', [AdminTaskController::class, 'restore'])->withTrashed()->middleware('can:tasks.manage')->name('tasks.restore');

        Route::get('/categories', [AdminCategoryController::class, 'index'])->middleware('can:categories.manage')->name('categories.index');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->middleware('can:categories.manage')->name('categories.store');
        Route::patch('/categories/{category}', [AdminCategoryController::class, 'update'])->middleware('can:categories.manage')->name('categories.update');
        Route::patch('/categories/{category}/status', [AdminCategoryController::class, 'updateStatus'])->middleware('can:categories.manage')->name('categories.status');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->middleware('can:categories.manage')->name('categories.destroy');

        Route::get('/notifications', [AnnouncementController::class, 'index'])->middleware('can:notifications.manage')->name('notifications.index');
        Route::post('/notifications', [AnnouncementController::class, 'store'])->middleware('can:notifications.manage')->name('notifications.store');
        Route::patch('/notifications/{announcement}', [AnnouncementController::class, 'update'])->middleware('can:notifications.manage')->name('notifications.update');
        Route::patch('/notifications/{announcement}/publish', [AnnouncementController::class, 'publish'])->middleware('can:notifications.manage')->name('notifications.publish');
        Route::patch('/notifications/{announcement}/cancel', [AnnouncementController::class, 'cancel'])->middleware('can:notifications.manage')->name('notifications.cancel');

        Route::get('/reports', [ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->middleware('can:reports.view')->name('reports.export');
        Route::get('/audit', [AuditLogController::class, 'index'])->middleware('can:audit_logs.view')->name('audit.index');
        Route::get('/settings', [SystemSettingController::class, 'index'])->middleware('can:settings.manage')->name('settings.index');
        Route::patch('/settings', [SystemSettingController::class, 'update'])->middleware('can:settings.manage')->name('settings.update');
        Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile');
    });
});
