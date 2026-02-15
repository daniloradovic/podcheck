<?php

use App\Http\Controllers\FeedCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FeedCheckController::class, 'index'])->name('home');
Route::post('/check', [FeedCheckController::class, 'check'])->name('feed.check');
Route::get('/report/{report}', [FeedCheckController::class, 'show'])->name('report.show');
