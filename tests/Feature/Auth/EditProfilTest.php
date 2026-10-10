<?php

use App\Enums\Peran;
use App\Filament\Pages\Auth\EditProfil;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class]);
    Filament::setCurrentPanel('alias');
});

function akunDenganSandi(Peran $peran, array $atribut = []): User
{
    $user = User::factory()->create(['password' => Hash::make('password'), ...$atribut])->assignRole($peran->value);
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    return $user;
}

it('pengguna dengan wajib_ganti_sandi diarahkan ke profil', function () {
    $user = akunDenganSandi(Peran::Pengguna, ['wajib_ganti_sandi' => true]);

    $this->actingAs($user)->get('/panel')->assertRedirect(Filament::getProfileUrl());
});

it('mengganti sandi di profil mencabut kewajiban dan mengeluarkan perangkat lain', function () {
    $user = akunDenganSandi(Peran::Pengguna, ['wajib_ganti_sandi' => true]);
    $lama = $user->password;
    Event::fake([OtherDeviceLogout::class]);

    $this->actingAs($user);

    Livewire::test(EditProfil::class)
        ->fillForm([
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'sandi-baru-123',
            'passwordConfirmation' => 'sandi-baru-123',
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->wajib_ganti_sandi)->toBeFalse()
        ->and($user->password)->not->toBe($lama)
        ->and(Hash::check('sandi-baru-123', $user->password))->toBeTrue();
    Event::assertDispatched(OtherDeviceLogout::class);

    $this->get('/panel')->assertOk();
});
