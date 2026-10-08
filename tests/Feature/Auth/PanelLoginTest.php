<?php

use App\Enums\Peran;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('alias');
});

it('tidak menyediakan rute registrasi', function () {
    $this->get('/panel/register')->assertNotFound();
    expect(Filament::getPanel('alias')->hasRegistration())->toBeFalse();
});

it('memakai halaman login Indonesia dengan tautan minta akses', function () {
    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('Masuk ke Alias FKIP')
        ->assertSee('@unsil.ac.id')
        ->assertSee('/minta-akses');
});

it('menolak login dengan kata sandi salah dan pesan generik', function () {
    $user = User::factory()->create(['email' => 'dosen.a@unsil.ac.id']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'salah'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);
});

it('mengirim surel reset kata sandi lewat antrean', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'dosen.a@unsil.ac.id']);

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('mengarahkan admin-alias tanpa MFA ke profil', function (Peran $peran) {
    $admin = User::factory()->create()->assignRole($peran->value);

    $this->actingAs($admin)->get('/panel')->assertRedirect(Filament::getPanel('alias')->getProfileUrl());
})->with([Peran::AdminAlias, Peran::SuperAdmin]);

it('mengizinkan admin-alias dengan MFA aktif', function () {
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($admin)->get('/panel')->assertOk();
});

it('tidak memaksa MFA untuk pengguna biasa', function () {
    $pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);

    $this->actingAs($pengguna)->get('/panel')->assertOk();
});

it('tetap mengizinkan admin tanpa MFA membuka profil', function () {
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);

    $this->actingAs($admin)->get(Filament::getPanel('alias')->getProfileUrl())->assertOk();
});

it('mengakhiri sesi lain setelah kata sandi berubah', function () {
    $user = User::factory()->create()->assignRole(Peran::Pengguna->value);

    $this->actingAs($user)
        ->withSession(['password_hash_web' => $user->getAuthPassword()])
        ->get('/panel')->assertOk();

    $user->forceFill(['password' => 'kata-sandi-baru-123'])->save();

    $this->withSession(['password_hash_web' => 'hash-lama-sesi-lain'])
        ->get('/panel')->assertRedirect();
});

it('tidak memakai tabindex positif agar Tab dari surel mendarat di kata sandi', function () {
    $this->get('/panel/login')->assertOk()->assertDontSee('tabindex="2"', false);
});

it('memaksa admin tanpa MFA ke profil saat mfa_aktif, dan tidak memaksa saat dimatikan', function () {
    $admin = User::factory()->create(['aktif' => true]);
    $admin->assignRole(Peran::AdminAlias->value);

    $this->actingAs($admin)->get('/panel')->assertRedirect(Filament::getPanel('alias')->getProfileUrl());

    config(['alias.mfa_aktif' => false]);

    $this->actingAs($admin)->get('/panel')->assertOk();
});
