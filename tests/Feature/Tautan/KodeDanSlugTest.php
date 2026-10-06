<?php

use App\Enums\Peran;
use App\Exceptions\KodeGagalDibangkitkan;
use App\Filament\Resources\SlugTerlarangResource\Pages\ManageSlugTerlarang;
use App\Models\SlugTerlarang;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Rules\SlugKustomValid;
use App\Support\Kode\DaftarSegmenRute;
use App\Support\Kode\PembangkitKode;
use App\Support\Kode\PemeriksaSlug;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\SlugTerlarangSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, SlugTerlarangSeeder::class]);
    Filament::setCurrentPanel('alias');
    Cache::flush();
});

it('membangkitkan 1.000 kode acak unik berformat base62 7 karakter', function () {
    $kode = collect(range(1, 1000))->map(fn () => app(PembangkitKode::class)->buat());

    expect($kode->unique())->toHaveCount(1000)
        ->and($kode->every(fn (string $k) => (bool) preg_match('/^[A-Za-z0-9]{7}$/', $k)))->toBeTrue();
});

it('melewati kandidat yang bentrok slug terlarang, segmen sistem, atau sudah ada', function () {
    $user = User::factory()->create();
    TautanPendek::factory()->milikPribadi($user)->create(['kode' => 'Terpakai']);

    $kandidat = ['PANEL', 'xjudolx', 'Terpakai', 'AmanSaja'];
    Str::createRandomStringsUsing(function () use (&$kandidat) {
        return array_shift($kandidat) ?? 'Cadangan1';
    });

    expect(app(PembangkitKode::class)->buat())->toBe('AmanSaja');
    Str::createRandomStringsNormally();
});

it('menaikkan panjang kode lalu gagal terkendali', function () {
    Str::createRandomStringsUsing(fn (int $p) => $p === 7 ? 'panel' : 'panel');
    expect(fn () => app(PembangkitKode::class)->buat())->toThrow(KodeGagalDibangkitkan::class);
    Str::createRandomStringsNormally();

    $panjang = [];
    Str::createRandomStringsUsing(function (int $p) use (&$panjang) {
        $panjang[] = $p;

        return $p === 7 ? 'login' : 'Berhasil8';
    });
    expect(app(PembangkitKode::class)->buat())->toBe('Berhasil8')
        ->and(array_count_values($panjang))->toBe([7 => 5, 8 => 1]);
    Str::createRandomStringsNormally();
});

it('memvalidasi slug kustom', function (string $slug, bool $valid) {
    $lolos = Validator::make(['slug' => $slug], ['slug' => [new SlugKustomValid]])->passes();

    expect($lolos)->toBe($valid);
})->with([
    'sah' => ['seminar-pmat-2026', true],
    'dinormalisasi' => ['  Seminar-PMAT-2026 ', true],
    'diawali strip' => ['-abc', false],
    'terlalu pendek' => ['ab', false],
    'strip ganda' => ['a--b', false],
    'diakhiri strip' => ['abc-', false],
    'spasi' => ['Seminar PMAT', false],
    'judi' => ['judol88', false],
    'panel' => ['panel', false],
    'Horizon' => ['Horizon', false],
    'api' => ['api', false],
    'lapor' => ['lapor', false],
    'kelembagaan' => ['rektor', false],
    'berawalan sama tetap boleh' => ['api-docs', true],
]);

it('menolak slug yang dipakai tautan terhapus (BR-04)', function () {
    $user = User::factory()->create();
    $t = TautanPendek::factory()->milikPribadi($user)->create(['kode' => 'sudah-ada', 'kode_kustom' => true]);

    expect(app(PemeriksaSlug::class)->periksa('sudah-ada'))->toBe('Slug sudah dipakai.');

    $t->delete();
    expect(app(PemeriksaSlug::class)->periksa('sudah-ada'))->toBe('Slug sudah dipakai.')
        ->and(app(PemeriksaSlug::class)->periksa('sudah-ada', kecualiId: $t->id))->toBeNull();
});

it('menegakkan namespace unit bila diaktifkan (BR-22)', function () {
    config(['alias.namespace_unit' => true]);
    $pmat = Unit::create(['kode' => 'PMAT', 'nama' => 'PMAT', 'jenis' => 'prodi', 'prefiks_slug' => 'pmat']);
    $anggota = User::factory()->create();
    $pmat->keanggotaan()->create(['user_id' => $anggota->id, 'peran_unit' => 'anggota']);
    $luar = User::factory()->create();

    expect(app(PemeriksaSlug::class)->periksa('pmat-seminar', $anggota))->toBeNull()
        ->and(app(PemeriksaSlug::class)->periksa('pmat-seminar', $luar))->toBe('Awalan slug ini dicadangkan untuk unit lain.')
        ->and(app(PemeriksaSlug::class)->periksa('pmat-seminar', $luar, $pmat))->toBeNull()
        ->and(app(PemeriksaSlug::class)->periksa('pmatx-seminar', $luar))->toBeNull();
});

it('membersihkan cache saat slug terlarang berubah', function () {
    expect(app(PemeriksaSlug::class)->periksa('rapat-resmi-baru'))->toBeNull();

    SlugTerlarang::create(['pola' => 'resmi-baru', 'jenis' => 'cadangan_kelembagaan', 'cara_cocok' => 'mengandung']);

    expect(app(PemeriksaSlug::class)->periksa('rapat-resmi-baru'))->not->toBeNull();
});

it('menyemai slug terlarang secara idempoten', function () {
    $jumlah = SlugTerlarang::count();
    $this->seed(SlugTerlarangSeeder::class);

    expect(SlugTerlarang::count())->toBe($jumlah)
        ->and(SlugTerlarang::where('pola', 'horizon')->exists())->toBeTrue()
        ->and(SlugTerlarang::where('pola', 'gacor')->where('cara_cocok', 'mengandung')->exists())->toBeTrue();
});

it('menghitung segmen rute dan pola kode (BR-36)', function () {
    $segmen = DaftarSegmenRute::hitung();

    expect($segmen)->toContain('panel', 'horizon', 'minta-akses', 'api', 'livewire');

    $pola = '#^'.DaftarSegmenRute::polaKode().'$#';
    foreach (['panel', 'panel+', 'horizon', 'api', 'lapor', 'minta-akses', 'privasi'] as $sistem) {
        expect(preg_match($pola, $sistem))->toBe(0, $sistem);
    }
    foreach (['Abc1234', 'api-docs', 'seminar-pmat-2026', 'panelx', 'xpanel'] as $kode) {
        expect(preg_match($pola, $kode))->toBe(1, $kode);
    }
});

it('membatasi resource slug terlarang dan melindungi cadangan sistem', function () {
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $super = User::factory()->create()->assignRole(Peran::SuperAdmin->value);
    $sistem = SlugTerlarang::where('pola', 'horizon')->first();
    $kelembagaan = SlugTerlarang::where('pola', 'rektor')->first();

    $this->actingAs(User::factory()->create()->assignRole(Peran::Pengguna->value))->get('/panel/slug-terlarang')->assertForbidden();
    $this->actingAs($admin)->get('/panel/slug-terlarang')->assertOk();

    expect($admin->can('delete', $sistem))->toBeFalse()
        ->and($admin->can('update', $sistem))->toBeFalse()
        ->and($super->can('update', $sistem))->toBeTrue()
        ->and($admin->can('delete', $kelembagaan))->toBeTrue();

    Livewire::actingAs($admin)->test(ManageSlugTerlarang::class)
        ->callAction(TestAction::make('create')->table(), ['pola' => 'Uji-Baru', 'jenis' => 'cadangan_kelembagaan', 'cara_cocok' => 'persis', 'aktif' => true])
        ->assertHasNoActionErrors();
    expect(SlugTerlarang::where('pola', 'uji-baru')->value('dibuat_oleh'))->toBe($admin->id);
});
