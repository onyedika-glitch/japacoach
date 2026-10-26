<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\MarkdownController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [MarkdownController::class, 'llms'])->name('llms');
Route::get('/llms-full.txt', [MarkdownController::class, 'llmsFull'])->name('llms-full');
Route::get('/ai.txt', [MarkdownController::class, 'aiTxt'])->name('ai-txt');

// Markdown versions of public pages. Caddy sends /<page>.md here, and the public page paths only when the
// request asks for text/markdown (browsers always get the Vue app's HTML).
Route::get('/{path}.md', [MarkdownController::class, 'show'])->where('path', '[A-Za-z0-9/_-]+')->name('md.file');
Route::options('/{path}.md', [MarkdownController::class, 'options'])->where('path', '[A-Za-z0-9/_-]+');
Route::get('/', [MarkdownController::class, 'negotiated'])->name('md.home');
Route::get('/{page}', [MarkdownController::class, 'negotiated'])->whereIn('page', ['pricing', 'terms', 'privacy', 'delete-account'])->name('md.page');
