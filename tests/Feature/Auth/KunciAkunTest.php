<?php

use App\Enums\Peran;
use App\Enums\PeristiwaLogin;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\LogLoginResource\Pages\ListLogLogins;
use App\Models\LogLogin;
use App\Models\User;
use App\Notifications\AkunTerkunci;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('alias');
});

function gagalkan(User $user, int $kali): void
{
    for ($i = 0; $i < $kali; $i++) {
        event(new Failed('web', $user, ['email' => $user->email, 'password' => 'salah']));
    }
}

it('membatasi percobaan login 5 per menit', function () {
    $user = User::factory()->create();

    $komponen = Livewire::test(Login::class);
    foreach (range(1, 6) as $_) {
        $komponen->fillForm(['email' => $user->email, 'password' => 'salah'])->call('authenticate');
    }

    $komponen->assertNotified();
    expect(LogLogin::where('peristiwa', PeristiwaLogin::Gagal)->count())->toBe(5);
});

it('mengunci akun setelah 10 gagal dan mengirim surel', function () {
    Notification::fake();
    $user = User::factory()->create();

    gagalkan($user, 9);
    expect($user->fresh()->terkunci())->toBeFalse();

    gagalkan($user, 1);

    expect($user->fresh()->terkunci())->toBeTrue()
        ->and($user->fresh()->terkunci_sampai->diffInMinutes(now(), true))->toBeGreaterThan(13)
        ->and(LogLogin::where('peristiwa', PeristiwaLogin::Terkunci)->count())->toBe(1);
    Notification::assertSentTo($user, AkunTerkunci::class);
});

it('menolak login benar saat akun terkunci dan mencatatnya', function () {
    $user = User::factory()->create();
    $user->forceFill(['terkunci_sampai' => now()->addMinutes(10)])->save();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth()->check())->toBeFalse()
        ->and(LogLogin::where('peristiwa', PeristiwaLogin::DitolakNonaktif)->count())->toBe(1);
});

it('menolak login benar untuk akun nonaktif', function () {
    $user = User::factory()->create(['aktif' => false]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(LogLogin::where('peristiwa', PeristiwaLogin::DitolakNonaktif)->exists())->toBeTrue();
});

it('menolak login benar dari domain surel di luar unsil', function () {
    $user = User::factory()->create(['email' => 'dosen@gmail.com']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(LogLogin::where('peristiwa', PeristiwaLogin::DitolakDomain)->exists())->toBeTrue();
});

it('mencatat login berhasil dan mengosongkan penghitung gagal', function () {
    $user = User::factory()->create();
    gagalkan($user, 3);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(LogLogin::where('peristiwa', PeristiwaLogin::Berhasil)->count())->toBe(1)
        ->and($user->fresh()->terakhir_masuk_pada)->not->toBeNull()
        ->and($user->fresh()->metode_login_terakhir)->toBe('kata_sandi');

    gagalkan($user, 9);
    expect($user->fresh()->terkunci())->toBeFalse();
});

it('mencatat logout dan reset kata sandi', function () {
    $user = User::factory()->create();

    event(new Logout('web', $user));
    event(new PasswordReset($user));

    expect(LogLogin::pluck('peristiwa')->map->value->all())->toEqualCanonicalizing(['keluar', 'reset_kata_sandi']);
});

it('mengakhiri sesi aktif akun yang kemudian dikunci', function () {
    $user = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->actingAs($user)->get('/panel')->assertOk();

    $user->forceFill(['terkunci_sampai' => now()->addMinutes(5)])->save();

    $this->get('/panel')->assertRedirect(Filament::getPanel('alias')->getLoginUrl());
    $this->assertGuest();
});

it('membatasi resource log login untuk pemegang log-login.lihat', function () {
    $super = User::factory()->create()->assignRole(Peran::SuperAdmin->value);
    $super->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);
    event(new LoginEvent('web', $pengguna, false));

    $this->actingAs($pengguna)->get('/panel/log-login')->assertForbidden();
    $this->actingAs($admin)->get('/panel/log-login')->assertForbidden();
    $this->actingAs($super)->get('/panel/log-login')->assertOk();

    Livewire::actingAs($super)->test(ListLogLogins::class)->assertCanSeeTableRecords(LogLogin::all());
});
