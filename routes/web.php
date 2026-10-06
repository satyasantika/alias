<?php

use App\Http\Controllers\Akses\MintaAksesController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->prefix('auth/google')->group(function () {
    Route::get('arahkan', [GoogleAuthController::class, 'arahkan'])->name('auth.google.arahkan');
    Route::get('kembali', [GoogleAuthController::class, 'kembali'])->name('auth.google.kembali');
});

Route::prefix('minta-akses')->group(function () {
    Route::get('/', [MintaAksesController::class, 'formulir'])->name('akses.formulir');
    Route::post('/', [MintaAksesController::class, 'kirim'])->middleware('throttle:minta-akses')->name('akses.kirim');
    Route::get('verifikasi/{permintaan}', [MintaAksesController::class, 'verifikasi'])->middleware('signed')->name('akses.verifikasi');
});
