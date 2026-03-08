<?php

use App\Http\Controllers\PaymentPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

// Public payment page accessed via QR code
Route::get('/pay/{token}', [PaymentPageController::class, 'show'])->name('payment.show');
Route::post('/pay/{token}/confirm', [PaymentPageController::class, 'confirm'])->name('payment.confirm');
