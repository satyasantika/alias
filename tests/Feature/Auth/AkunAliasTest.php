<?php

use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Validator;

it('menerima surel domain unsil dan menolak yang lain', function (string $surel, bool $valid) {
    $lolos = Validator::make(['e' => $surel], ['e' => [new SurelDomainUnsil]])->passes();

    expect($lolos)->toBe($valid);
})->with([
    ['dosen@unsil.ac.id', true],
    ['DOSEN@UNSIL.AC.ID', true],
    ['a@unsil.ac.id.evil.com', false],
    ['a@gmail.com', false],
    ['a@sub.unsil.ac.id', false],
    ['a@evil.com@unsil.ac.id', false],
]);

it('menolak pengguna nonaktif masuk panel', function () {
    $panel = Filament::getPanel('alias');

    expect(User::factory()->create(['aktif' => false])->canAccessPanel($panel))->toBeFalse()
        ->and(User::factory()->create()->canAccessPanel($panel))->toBeTrue();
});

it('menolak pengguna terkunci masuk panel', function () {
    $panel = Filament::getPanel('alias');
    $user = User::factory()->create();
    $user->forceFill(['terkunci_sampai' => now()->addMinutes(10)])->save();

    expect($user->canAccessPanel($panel))->toBeFalse();

    $user->forceFill(['terkunci_sampai' => now()->subMinute()])->save();
    expect($user->fresh()->canAccessPanel($panel))->toBeTrue();
});

it('menyembunyikan rahasia MFA dari serialisasi', function () {
    $user = User::factory()->create(['aktif' => true]);
    $user->forceFill(['app_authentication_secret' => 'RAHASIA'])->save();

    expect($user->fresh()->toArray())->not->toHaveKey('app_authentication_secret')
        ->and($user->fresh()->app_authentication_secret)->toBe('RAHASIA');
});

it('tidak memakai SurelDomainUnsil untuk string kosong', function () {
    expect(SurelDomainUnsil::lolos(null))->toBeFalse()->and(SurelDomainUnsil::lolos(''))->toBeFalse();
});
