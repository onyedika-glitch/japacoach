<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
