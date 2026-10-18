<?php

use App\Filament\Exports\KunjunganExporter;
use App\Filament\Imports\TautanImporter;
use App\Models\Export;
use App\Models\Import;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): dua antrean di database, worker dinyalakan penjadwal.
 * Catatan: phpunit.xml memakai cache/antrean/sesi `array`/`sync`; uji ini memeriksa KONFIGURASI, bukan perilaku driver database.
 */
it('mendefinisikan koneksi database dan database-berat dengan retry_after di atas timeout worker', function () {
    $ringan = config('queue.connections.database');
    $berat = config('queue.connections.database-berat');

    expect($ringan['driver'])->toBe('database')
        ->and($ringan['after_commit'])->toBeTrue()
        ->and($ringan['retry_after'])->toBeGreaterThan(60)   // --timeout worker default
        ->and($berat['driver'])->toBe('database')
        ->and($berat['queue'])->toBe('berat')
        ->and($berat['after_commit'])->toBeTrue()
        ->and($berat['retry_after'])->toBeGreaterThan(900);  // --timeout worker berat
});

it('menjadwalkan dua worker, detak penjadwal, dan pemangkasan antrean', function () {
    $jadwal = collect(app(Schedule::class)->events())
        ->map(fn ($e) => (string) ($e->command ?? $e->description))
        ->implode("\n");

    expect($jadwal)
        ->toContain('queue:work database --queue=default --stop-when-empty')
        ->toContain('queue:work database-berat --queue=berat --stop-when-empty')
        ->toContain('detak-penjadwal')
        ->toContain('queue:prune-failed')
        ->toContain('queue:prune-batches')
        ->not->toContain('horizon');
});

it('mengarahkan impor dan ekspor Filament ke koneksi job berat dan antrean berat', function () {
    // Bawaan produksi `database-berat`; phpunit.xml menyetelnya `sync` agar impor/ekspor berjalan serentak dalam uji.
    expect(file_get_contents(config_path('queue.php')))->toContain("env('QUEUE_CONNECTION_BERAT', 'database-berat')");

    config(['queue.berat' => 'database-berat']);

    foreach ([new TautanImporter(new Import, [], []), new KunjunganExporter(new Export, [], [])] as $pekerja) {
        expect($pekerja->getJobConnection())->toBe('database-berat')->and($pekerja->getJobQueue())->toBe('berat');
    }
});

it('memberi aplikasi kode pendek untuk kunci cache bersama', function () {
    expect(config('app.kode'))->toBe('alias');
});
