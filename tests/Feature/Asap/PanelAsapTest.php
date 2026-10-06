<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

it('menampilkan halaman login panel', function () {
    $this->get('/admin/login')->assertOk();
});

it('mengizinkan pengguna seeder masuk ke panel', function () {
    $this->seed(DatabaseSeeder::class);
    $user = User::where('email', 'superadmin@unsil.ac.id')->firstOrFail();

    $this->actingAs($user)->get('/admin')->assertOk();
});

it('menjawab pemeriksaan kesehatan /up', function () {
    $this->get('/up')->assertOk();
});

it('memakai lokal dan zona waktu Indonesia', function () {
    expect(app()->getLocale())->toBe('id')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(Carbon::getLocale())->toBe('id');
});

it('membuka daftar pengguna di panel', function () {
    $this->seed(DatabaseSeeder::class);
    $user = User::where('email', 'superadmin@unsil.ac.id')->firstOrFail();

    $this->actingAs($user)->get('/admin/users')->assertOk();
});
