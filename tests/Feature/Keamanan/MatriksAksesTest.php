<?php

use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Facades\Filament;

/**
 * PRD §4.3 — setiap sel matriks untuk akses halaman panel/HTTP.
 * Kolom: super-admin, admin-alias, pengelola-unit, pengguna, pemantau. 1 = boleh (200), 0 = ditolak (403/404).
 */
beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class, PengaturanSeeder::class]);
    Filament::setCurrentPanel('alias');
    $pmat = Unit::where('kode', 'PMAT')->first();

    $buat = function (Peran $peran) {
        $u = User::factory()->create()->assignRole($peran->value);
        if ($peran->peranAdmin()) {
            $u->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        }

        return $u;
    };
    $this->akun = [
        'super-admin' => $buat(Peran::SuperAdmin),
        'admin-alias' => $buat(Peran::AdminAlias),
        'pengelola-unit' => $buat(Peran::PengelolaUnit),
        'pengguna' => $buat(Peran::Pengguna),
        'pemantau' => $buat(Peran::Pemantau),
    ];
    app(TambahAnggotaUnit::class)->jalankan($pmat, $this->akun['pengelola-unit'], PeranUnit::Pengelola, $this->akun['super-admin']);
    app(TambahAnggotaUnit::class)->jalankan($pmat, $this->akun['pengguna'], PeranUnit::Anggota, $this->akun['super-admin']);
});

$halaman = [
    // modul / aksi => [path, super, admin, pengelola, pengguna, pemantau]
    'Tautan — lihat daftar' => ['/panel/tautan', 1, 1, 1, 1, 0],
    'Tautan — buat' => ['/panel/tautan/create', 1, 1, 1, 1, 0],
    'Tautan — setujui slug (antrean)' => ['/panel/tautan/persetujuan', 1, 1, 0, 0, 0],
    'Analitik agregat — statistik fakultas' => ['/panel/statistik-fakultas', 1, 1, 1, 0, 1],
    'Pengguna — lihat' => ['/panel/pengguna', 1, 1, 1, 0, 0],
    'Pengguna — kelola (buat)' => ['/panel/pengguna/create', 1, 1, 0, 0, 0],
    'Unit — lihat' => ['/panel/unit', 1, 1, 1, 1, 1],
    'Unit — kelola (buat)' => ['/panel/unit/create', 1, 1, 0, 0, 0],
    'Permintaan akses — proses' => ['/panel/permintaan-akses', 1, 1, 0, 0, 0],
    'Laporan penyalahgunaan — kelola' => ['/panel/laporan-penyalahgunaan', 1, 1, 0, 0, 0],
    'Slug terlarang' => ['/panel/slug-terlarang', 1, 1, 0, 0, 0],
    'Aturan domain' => ['/panel/aturan-domain', 1, 1, 0, 0, 0],
    'Pengaturan sistem' => ['/panel/pengaturan-sistem', 1, 0, 0, 0, 0],
    'Horizon' => ['/horizon', 1, 0, 0, 0, 0],
    'Log aktivitas' => ['/panel/log-aktivitas', 1, 1, 0, 0, 0],
    'Log login' => ['/panel/log-login', 1, 0, 0, 0, 0],
    'Dasbor' => ['/panel', 1, 1, 1, 1, 1],
];

$peran = ['super-admin', 'admin-alias', 'pengelola-unit', 'pengguna', 'pemantau'];

foreach ($halaman as $nama => $baris) {
    foreach ($peran as $i => $namaPeran) {
        $boleh = $baris[$i + 1] === 1;
        it("{$namaPeran} ".($boleh ? 'boleh' : 'tidak boleh')." membuka {$nama}", function () use ($baris, $namaPeran, $boleh) {
            $respons = $this->actingAs($this->akun[$namaPeran])->get($baris[0]);

            if ($boleh) {
                expect($respons->getStatusCode())->toBeIn([200], "{$namaPeran} {$baris[0]}");
            } else {
                expect($respons->getStatusCode())->toBeIn([403, 404], "{$namaPeran} {$baris[0]}");
            }
        });
    }
}

// Permission yang tidak punya halaman sendiri (aksi): cek langsung terhadap matriks.
$izinAksi = [
    'tautan.slug-kustom' => [1, 1, 1, 1, 0], 'tautan.transfer' => [1, 1, 1, 1, 0], 'tautan.blokir' => [1, 1, 0, 0, 0],
    'tautan.atur-lanjutan' => [1, 1, 0, 0, 0], 'tautan.impor' => [1, 1, 0, 0, 0], 'tautan.tanpa-kuota' => [1, 1, 0, 0, 0],
    'kunjungan.lihat-rinci' => [1, 1, 1, 1, 0], 'analitik.ekspor' => [1, 1, 1, 1, 1], 'pengguna.atur-peran-admin' => [1, 0, 0, 0, 0],
    'unit.kelola-anggota' => [1, 1, 1, 0, 0],
];

foreach ($izinAksi as $izin => $baris) {
    foreach ($peran as $i => $namaPeran) {
        it("{$namaPeran} ".($baris[$i] ? 'memiliki' : 'tidak memiliki')." aksi {$izin}", function () use ($izin, $namaPeran, $baris, $i) {
            expect($this->akun[$namaPeran]->can($izin))->toBe($baris[$i] === 1);
        });
    }
}

it('menolak tamu pada seluruh halaman panel dan mengarahkan ke login', function () {
    foreach (['/panel', '/panel/tautan', '/panel/pengguna', '/panel/pengaturan-sistem', '/panel/log-login'] as $path) {
        $this->get($path)->assertRedirect();
    }
    $this->get('/horizon')->assertStatus(403);
});

it('menolak akses lintas record: UUID tautan orang lain tidak dapat dibuka (IDOR)', function () {
    $lain = TautanPendek::factory()->milikPribadi(User::factory()->create())->create();

    foreach (['', '/edit'] as $akhiran) {
        $status = $this->actingAs($this->akun['pengguna'])->get("/panel/tautan/{$lain->id}{$akhiran}")->getStatusCode();
        expect($status)->toBeIn([403, 404]);
    }
    expect($this->actingAs($this->akun['pengguna'])->get("/panel/tautan/{$lain->id}/qr.svg")->getStatusCode())->toBe(403);
});
