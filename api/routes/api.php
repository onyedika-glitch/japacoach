<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MockExamController;
use App\Http\Controllers\Api\PracticeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\SpeakingAttemptController;
use App\Http\Controllers\Api\StudyPlanController;
use App\Http\Controllers\Api\WritingController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
});

Route::get('/billing/plans', [BillingController::class, 'plans'])->name('billing.plans');
Route::post('/webhooks/bachs', [PaymentController::class, 'webhook'])->name('webhooks.bachs');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
    Route::patch('/me', [ProfileController::class, 'update'])->name('me.update');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('speaking')->name('speaking.')->group(function () {
        Route::get('/attempts', [SpeakingAttemptController::class, 'index'])->name('attempts.index');
        Route::post('/attempts', [SpeakingAttemptController::class, 'store'])->middleware('throttle:ai')->name('attempts.store');
        Route::get('/attempts/{attempt}', [SpeakingAttemptController::class, 'show'])->whereNumber('attempt')->name('attempts.show');
        Route::post('/attempts/{attempt}/answers', [SpeakingAttemptController::class, 'uploadAnswer'])->whereNumber('attempt')->middleware('throttle:uploads')->name('attempts.answers');
        Route::post('/attempts/{attempt}/submit', [SpeakingAttemptController::class, 'submit'])->whereNumber('attempt')->name('attempts.submit');
        Route::get('/answers/{answer}/audio', [SpeakingAttemptController::class, 'audio'])->name('answers.audio');
    });

    Route::prefix('writing')->name('writing.')->group(function () {
        Route::get('/tasks', [WritingController::class, 'tasks'])->name('tasks.index');
        Route::get('/tasks/{task}', [WritingController::class, 'task'])->name('tasks.show');
        Route::get('/attempts', [WritingController::class, 'index'])->name('attempts.index');
        Route::post('/attempts', [WritingController::class, 'store'])->middleware('throttle:ai')->name('attempts.store');
        Route::get('/attempts/{attempt}', [WritingController::class, 'show'])->whereNumber('attempt')->name('attempts.show');
    });

    Route::prefix('practice')->name('practice.')->group(function () {
        Route::get('/{module}/tests', [PracticeController::class, 'tests'])->whereIn('module', ['reading', 'listening'])->name('tests.index');
        Route::post('/{module}/tests/{test}/attempts', [PracticeController::class, 'start'])->whereIn('module', ['reading', 'listening'])->name('attempts.store');
        Route::get('/attempts/{attempt}', [PracticeController::class, 'show'])->whereNumber('attempt')->name('attempts.show');
        Route::patch('/attempts/{attempt}', [PracticeController::class, 'save'])->whereNumber('attempt')->name('attempts.update');
        Route::post('/attempts/{attempt}/submit', [PracticeController::class, 'submit'])->whereNumber('attempt')->name('attempts.submit');
    });

    Route::get('/mock-exams', [MockExamController::class, 'index'])->name('mock-exams.index');
    Route::post('/mock-exams', [MockExamController::class, 'store'])->name('mock-exams.store');
    Route::get('/mock-exams/{exam}', [MockExamController::class, 'show'])->whereNumber('exam')->name('mock-exams.show');

    Route::get('/study-plan', [StudyPlanController::class, 'show'])->name('study-plan.show');
    Route::post('/study-plan/generate', [StudyPlanController::class, 'generate'])->middleware('throttle:ai')->name('study-plan.generate');

    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->middleware('throttle:ai')->name('billing.checkout');
    Route::get('/billing/payments', [BillingController::class, 'payments'])->name('billing.payments');
    Route::get('/referrals', ReferralController::class)->name('referrals');

    Route::middleware(EnsureUserIsAdmin::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats'])->name('stats');
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::get('/payments', [AdminController::class, 'payments'])->name('payments.index');
        Route::get('/content', [AdminController::class, 'content'])->name('content.index');
        Route::post('/content/generate', [AdminController::class, 'generate'])->name('content.generate');
        Route::patch('/content/{type}/{id}', [AdminController::class, 'updateContent'])->whereIn('type', ['speaking', 'writing', 'practice'])->whereNumber('id')->name('content.update');
    });
});
