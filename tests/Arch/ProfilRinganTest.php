<?php

use App\Enums\Izin;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): MariaDB native, cache/sesi/antrean di database, tanpa Redis dan Horizon.
 * Uji statis (berkas dan kode); tidak membutuhkan basis data.
 */
dataset('contoh env', ['.env.example', '.env.production.example']);

it('memakai MariaDB dan driver database pada contoh env', function (string $berkas) {
    $isi = file_get_contents(base_path($berkas));

    expect($isi)
        ->toContain("\nDB_CONNECTION=mariadb\n")
        ->toContain("\nDB_HOST=host.docker.internal\n")
        ->toContain("\nDB_DATABASE=db_alias\n")
        ->toContain("\nSESSION_DRIVER=database\n")
        ->toContain("\nQUEUE_CONNECTION=database\n")
        ->toContain("\nCACHE_STORE=database\n")
        ->toContain("\nAPP_KODE=alias\n")
        ->not->toMatch('/^REDIS_/m')
        ->not->toMatch('/^DB_CONNECTION=sqlite/m')
        ->not->toContain('_DRIVER=redis')
        ->not->toContain('QUEUE_CONNECTION=redis')
        ->not->toContain('CACHE_STORE=redis');
})->with('contoh env');

it('menguji pada MariaDB db_alias_testing, bukan SQLite, dengan driver array/sync untuk cache, sesi, dan antrean', function () {
    $env = simplexml_load_file(base_path('phpunit.xml'))->php->env;
    $nilai = [];
    foreach ($env as $e) {
        $nilai[(string) $e['name']] = (string) $e['value'];
    }

    expect($nilai)->toMatchArray([
        'DB_CONNECTION' => 'mariadb',
        'DB_HOST' => 'host.docker.internal',
        'DB_DATABASE' => 'db_alias_testing',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ]);
});

it('tidak memasang Horizon', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->not->toHaveKey('laravel/horizon')
        ->and(file_exists(config_path('horizon.php')))->toBeFalse()
        ->and(file_exists(app_path('Providers/HorizonServiceProvider.php')))->toBeFalse()
        ->and(file_get_contents(base_path('bootstrap/providers.php')))->not->toContain('Horizon')
        ->and(file_get_contents(base_path('composer.lock')))->not->toContain('"name": "laravel/horizon"');
});

it('tidak merujuk Horizon di kode, jadwal, atau izin', function () {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $berkas) {
        if ($berkas->getExtension() === 'php') {
            expect(file_get_contents($berkas->getPathname()))->not->toContain('Laravel\\Horizon')->not->toContain('horizon.lihat');
        }
    }

    expect(file_get_contents(base_path('routes/console.php')))->not->toContain('horizon:');
});

it('tidak memakai Cache::lock di kode aplikasi (kunci lewat transaksi + lockForUpdate, §1b)', function () {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $berkas) {
        if ($berkas->getExtension() === 'php') {
            expect(file_get_contents($berkas->getPathname()))
                ->not->toContain('Cache::lock')
                ->not->toContain('Cache::store(')
                ->not->toMatch('/\bcache\(\)->lock\(/');
        }
    }
});

it('tidak membandingkan nama driver dengan "mysql" saja (driver mariadb ikut terlewat)', function () {
    // Pengecualian: migrasi tautan_pendek yang sudah dirilis tidak boleh diubah (fix: migrasi baru
    // 2026_10_17_100100_...). Hapus pengecualian ini bila pemilik menyetujui koreksi guard pada migrasi lama.
    $dikecualikan = ['2026_10_07_100000_create_tautan_pendek_table.php'];

    foreach ([app_path(), database_path()] as $akar) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar)) as $berkas) {
            if ($berkas->getExtension() !== 'php' || in_array($berkas->getFilename(), $dikecualikan, true)) {
                continue;
            }

            expect(preg_match('/getDriverName\(\)\s*[!=]==?\s*[\'"]mysql[\'"]/', file_get_contents($berkas->getPathname())))
                ->toBe(0, $berkas->getPathname().' membandingkan getDriverName() dengan mysql');
        }
    }
});

it('menetapkan migrasi data izin horizon.lihat -> pemantauan.lihat', function () {
    expect(glob(database_path('migrations/*ganti_izin_horizon_lihat*.php')))->toHaveCount(1)
        ->and(Izin::tryFrom('horizon.lihat'))->toBeNull()
        ->and(Izin::PemantauanLihat->value)->toBe('pemantauan.lihat');
});
