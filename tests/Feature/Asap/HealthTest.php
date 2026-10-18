<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * GET /api/health (STANDAR-TEKNIS §1b): DB, job tertunda > 10 menit, failed_jobs 24 jam, detak penjadwal; tanpa Redis.
 */
function sisipJob(int $tersediaPada, ?int $dipesanPada = null): void
{
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
        'reserved_at' => $dipesanPada, 'available_at' => $tersediaPada, 'created_at' => $tersediaPada,
    ]);
}

beforeEach(fn () => Cache::flush());

it('mengembalikan 200 dengan status DB, antrean, dan detak penjadwal tanpa Redis', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson(['db' => 'ok', 'job_tertunda' => 0, 'job_gagal_24j' => 0, 'detak_penjadwal' => 'belum-ada'])
        ->assertJsonStructure(['app', 'versi', 'db', 'job_tertunda', 'job_gagal_24j', 'detak_penjadwal'])
        ->assertJsonMissing(['redis']);
});

it('menghitung job tertunda lebih dari 10 menit dan mengabaikan job baru atau yang sedang diproses', function () {
    sisipJob(now()->subMinutes(11)->getTimestamp());          // tertunda
    sisipJob(now()->subMinutes(30)->getTimestamp());          // tertunda
    sisipJob(now()->subMinutes(2)->getTimestamp());           // masih wajar
    sisipJob(now()->subMinutes(30)->getTimestamp(), now()->subMinutes(1)->getTimestamp()); // sedang diproses

    $this->getJson('/api/health')->assertOk()->assertJson(['job_tertunda' => 2]);
});

it('menghitung failed_jobs 24 jam terakhir', function () {
    foreach ([now()->subHours(2), now()->subHours(23), now()->subDays(3)] as $waktu) {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'uji', 'failed_at' => $waktu,
        ]);
    }

    $this->getJson('/api/health')->assertOk()->assertJson(['job_gagal_24j' => 2]);
});

it('membaca detak penjadwal: ok, terlambat setelah 5 menit', function () {
    $kunci = config('app.kode').':detak-penjadwal';

    Cache::put($kunci, now()->subMinute()->getTimestamp(), 600);
    $this->getJson('/api/health')->assertJson(['detak_penjadwal' => 'ok']);

    Cache::put($kunci, now()->subMinutes(6)->getTimestamp(), 600);
    $this->getJson('/api/health')->assertJson(['detak_penjadwal' => 'terlambat']);
});

it('mengembalikan 503 hanya saat basis data gagal', function () {
    DB::partialMock()->shouldReceive('select')->andThrow(new RuntimeException('db mati'));

    $this->getJson('/api/health')->assertStatus(503)->assertJson(['db' => 'gagal']);
});
