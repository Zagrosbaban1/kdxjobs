<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LegacyController;
use Illuminate\Support\Facades\Route;

Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
Route::post('/jobs/{job}/apply', [JobController::class, 'apply'])
    ->middleware(['auth', 'role:jobseeker'])
    ->name('jobs.apply');
Route::post('/jobs/{job}/saved', [JobController::class, 'toggleSaved'])
    ->middleware(['auth', 'role:jobseeker'])
    ->name('jobs.saved.toggle');

Route::get('/companies', [ContentController::class, 'companies'])->name('companies.index');
Route::get('/blog', [ContentController::class, 'blog'])->name('blog.index');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:jobseeker'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'user'])->name('user.dashboard');
});

Route::middleware(['auth', 'role:company'])->group(function (): void {
    Route::get('/company/dashboard', [DashboardController::class, 'company'])->name('company.dashboard');
});

Route::middleware(['auth', 'role:admin,superadmin'])->group(function (): void {
    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');
});

Route::middleware(['auth', 'role:company,admin,superadmin'])->group(function (): void {
    Route::post('/dashboard/jobs', [DashboardController::class, 'storeJob'])->name('dashboard.jobs.store');
    Route::post('/dashboard/applications/{application}', [DashboardController::class, 'updateApplication'])->name('dashboard.applications.update');
});

Route::match(['get', 'post', 'options'], '/api', [LegacyController::class, 'api'])
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

Route::get('/assets/{path}', [LegacyController::class, 'asset'])
    ->where('path', '.*');

Route::match(['get', 'post'], '/', [LegacyController::class, 'handle'])
    ->name('home')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

Route::match(['get', 'post'], '/index.php', [LegacyController::class, 'handle'])
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
