<?php

use App\Filament\Concerns\MemakaiAntreanBerat;
use App\Filament\Exports\KunjunganExporter;
use App\Filament\Exports\LaporanModerasiExporter;
use App\Filament\Exports\RekapTautanExporter;
use App\Filament\Exports\RekapUnitExporter;
use App\Filament\Imports\PenggunaImporter;
use App\Filament\Imports\TautanImporter;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): hanya dua antrean (`default`, `berat`); tanpa Redis; pencatatan kunjungan
 * lewat defer(), bukan job. Uji statis; tidak membutuhkan basis data.
 */
function berkasPhpApp(): iterable
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $berkas) {
        if ($berkas->getExtension() === 'php') {
            yield $berkas->getPathname() => file_get_contents($berkas->getPathname());
        }
    }
}

it('hanya memakai antrean default dan berat', function () {
    $diizinkan = ['default', 'berat'];

    foreach (berkasPhpApp() as $jalur => $isi) {
        preg_match_all('/(?:onQueue|getJobQueue)\([^)]*?[\'"]([^\'"]+)[\'"]|\$queue\s*=\s*[\'"]([^\'"]+)[\'"]/', $isi, $cocok);

        foreach (array_filter(array_merge($cocok[1], $cocok[2])) as $nama) {
            expect($nama)->toBeIn($diizinkan, str_replace(app_path().'/', '', $jalur)." memakai antrean '{$nama}'");
        }
    }
});

it('hanya memakai koneksi antrean database atau database-berat', function () {
    expect(array_keys(config('queue.connections')))->toContain('database', 'database-berat');

    foreach (berkasPhpApp() as $jalur => $isi) {
        preg_match_all('/(?:onConnection|getJobConnection)\([^)]*?[\'"]([^\'"]+)[\'"]|\$connection\s*=\s*[\'"]([^\'"]+)[\'"]|return\s+[\'"](database-berat)[\'"]/', $isi, $cocok);

        foreach (array_filter(array_merge($cocok[1], $cocok[2], $cocok[3])) as $nama) {
            expect($nama)->toBeIn(['database', 'database-berat'], str_replace(app_path().'/', '', $jalur)." memakai koneksi '{$nama}'");
        }
    }
});

it('tidak memakai Redis di kode aplikasi', function () {
    foreach (berkasPhpApp() as $jalur => $isi) {
        expect($isi)->not->toContain('Redis::')->not->toContain('Illuminate\\Support\\Facades\\Redis');
    }
});

it('mencatat kunjungan lewat defer() sebagai aksi, bukan job berantrean', function () {
    expect(file_exists(app_path('Jobs/CatatKunjungan.php')))->toBeFalse()
        ->and(file_get_contents(app_path('Actions/Kunjungan/CatatKunjungan.php')))->not->toContain('ShouldQueue')
        ->and(file_get_contents(app_path('Http/Controllers/Pendek/AlihkanTautanController.php')))->toContain('defer(')
        ->and(file_get_contents(app_path('Http/Controllers/Pendek/AlihkanTautanController.php')))->not->toContain('Dispatcher');
});

it('mengarahkan semua impor dan ekspor Filament ke antrean berat', function () {
    $kelas = [
        KunjunganExporter::class, LaporanModerasiExporter::class,
        RekapTautanExporter::class, RekapUnitExporter::class,
        PenggunaImporter::class, TautanImporter::class,
    ];

    foreach ($kelas as $nama) {
        expect(in_array(MemakaiAntreanBerat::class, class_uses_recursive($nama), true))->toBeTrue($nama);
    }
});
