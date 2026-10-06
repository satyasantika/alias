<?php

use App\Enums\Izin;
use App\Enums\Peran;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PenggunaAwalSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Support\Facades\Gate;

// Kolom: super-admin, admin-alias, pengelola-unit, pengguna, pemantau (03-SKEMA §6.1).
$matriks = [
    'tautan.lihat' => [1, 1, 1, 1, 0],
    'tautan.buat' => [1, 1, 1, 1, 0],
    'tautan.ubah' => [1, 1, 1, 1, 0],
    'tautan.nonaktifkan' => [1, 1, 1, 1, 0],
    'tautan.hapus' => [1, 1, 1, 1, 0],
    'tautan.slug-kustom' => [1, 1, 1, 1, 0],
    'tautan.transfer' => [1, 1, 1, 1, 0],
    'tautan.setujui' => [1, 1, 0, 0, 0],
    'tautan.blokir' => [1, 1, 0, 0, 0],
    'tautan.atur-lanjutan' => [1, 1, 0, 0, 0],
    'tautan.tanpa-kuota' => [1, 1, 0, 0, 0],
    'tautan.impor' => [1, 1, 0, 0, 0],
    'analitik.lihat' => [1, 1, 1, 1, 0],
    'analitik.lihat-agregat' => [1, 1, 1, 0, 1],
    'analitik.ekspor' => [1, 1, 1, 1, 1],
    'kunjungan.lihat-rinci' => [1, 1, 1, 1, 0],
    'pengguna.lihat' => [1, 1, 1, 0, 0],
    'pengguna.kelola' => [1, 1, 0, 0, 0],
    'pengguna.atur-peran-admin' => [1, 0, 0, 0, 0],
    'unit.lihat' => [1, 1, 1, 1, 1],
    'unit.kelola' => [1, 1, 0, 0, 0],
    'unit.kelola-anggota' => [1, 1, 1, 0, 0],
    'akses.proses' => [1, 1, 0, 0, 0],
    'moderasi.kelola' => [1, 1, 0, 0, 0],
    'slug-terlarang.kelola' => [1, 1, 0, 0, 0],
    'aturan-domain.kelola' => [1, 1, 0, 0, 0],
    'audit.lihat' => [1, 1, 0, 0, 0],
    'pengaturan.kelola' => [1, 0, 0, 0, 0],
    'horizon.lihat' => [1, 0, 0, 0, 0],
    'log-login.lihat' => [1, 0, 0, 0, 0],
];

$urutan = [Peran::SuperAdmin, Peran::AdminAlias, Peran::PengelolaUnit, Peran::Pengguna, Peran::Pemantau];

beforeEach(fn () => $this->seed(PeranDanIzinSeeder::class));

it('mencakup seluruh permission PRD', function () use ($matriks) {
    expect(array_keys($matriks))->toEqualCanonicalizing(array_map(fn (Izin $i) => $i->value, Izin::cases()))
        ->and(Permission::count())->toBe(count($matriks));
});

foreach ($urutan as $kolom => $peran) {
    it("memberi {$peran->value} tepat permission pada matriks", function () use ($matriks, $kolom, $peran) {
        $diharapkan = array_keys(array_filter($matriks, fn (array $baris) => $baris[$kolom] === 1));
        $dimiliki = Role::findByName($peran->value)->permissions->pluck('name')->all();

        expect($dimiliki)->toEqualCanonicalizing($diharapkan);
    });
}

it('idempoten saat dijalankan dua kali', function () {
    $this->seed(PeranDanIzinSeeder::class);

    expect(Role::count())->toBe(5)->and(Permission::count())->toBe(count(Izin::cases()));
});

it('membatasi horizon: pengguna 403, super-admin lolos', function () {
    $pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $super = User::factory()->create()->assignRole(Peran::SuperAdmin->value);
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);

    expect(Gate::forUser($pengguna)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser($super)->allows('viewHorizon'))->toBeTrue();

    $this->actingAs($pengguna)->get('/horizon')->assertForbidden();
});

it('meloloskan super-admin lewat Gate::before', function () {
    $super = User::factory()->create()->assignRole(Peran::SuperAdmin->value);

    expect($super->can('izin-apa-saja'))->toBeTrue();
});

it('membuat pengguna awal dengan peran yang benar dan gagal tanpa SEED_PASSWORD', function () {
    $this->seed(PenggunaAwalSeeder::class);

    expect(User::where('email', 'dekan@unsil.ac.id')->first()->hasRole('pemantau'))->toBeTrue()
        ->and(User::count())->toBe(7);

    config(['alias.seed_password' => null]);
    expect(fn () => $this->seed(PenggunaAwalSeeder::class))->toThrow(RuntimeException::class);
});
