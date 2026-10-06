<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->prefix('auth/google')->group(function () {
    Route::get('arahkan', [GoogleAuthController::class, 'arahkan'])->name('auth.google.arahkan');
    Route::get('kembali', [GoogleAuthController::class, 'kembali'])->name('auth.google.kembali');
});
