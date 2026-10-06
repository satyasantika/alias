<?php

use App\Enums\KategoriLaporan;
use App\Enums\StatusTautan;
use App\Jobs\PindaiUlangAturan;
use App\Models\AturanDomain;
use App\Models\LaporanPenyalahgunaan;
use App\Models\SlugTerlarang;
use App\Models\TautanPendek;
use App\Models\User;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\SlugTerlarangSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->pemilik = User::factory()->create();
    $this->buat = fn (string $kode, string $host, ?string $status = null) => TautanPendek::factory()->milikPribadi($this->pemilik)
        ->when($status, fn ($f) => $f->denganStatus($status))
        ->create(['kode' => $kode, 'url_tujuan' => "https://{$host}/x", 'host_tujuan' => $host]);
});

it('menandai dua tautan aktif ke domain yang baru diblokir tanpa memblokir otomatis', function () {
    $a = ($this->buat)('Tdm0001', 'contoh.com');
    $b = ($this->buat)('Tdm0002', 'contoh.com');
    $lain = ($this->buat)('Tdm0003', 'aman.com');
    $mati = ($this->buat)('Tdm0004', 'contoh.com', 'dinonaktifkan');
    $subdomain = ($this->buat)('Tdm0005', 'www.contoh.com');

    AturanDomain::create(['pola_host' => 'contoh.com', 'jenis' => 'blokir', 'alasan' => 'uji']);

    $laporan = LaporanPenyalahgunaan::where('ip_hash', PindaiUlangAturan::HASH_SISTEM)->get();
    expect($laporan)->toHaveCount(2)
        ->and($laporan->pluck('tautan_pendek_id')->all())->toEqualCanonicalizing([$a->id, $b->id])
        ->and($laporan[0]->kategori)->toBe(KategoriLaporan::Lainnya)
        ->and($laporan[0]->keterangan)->toContain('Melanggar aturan baru')->toContain('contoh.com')
        ->and($laporan[0]->status->value)->toBe('baru');

    // tidak memblokir: tautan tetap aktif dan masih 302
    expect($a->fresh()->status)->toBe(StatusTautan::Aktif);
    $this->get('/Tdm0001')->assertStatus(302);
    $this->get('/Tdm0002')->assertStatus(302);
});

it('mendukung pola wildcard subdomain', function () {
    ($this->buat)('Wld0001', 'contoh.xyz');
    ($this->buat)('Wld0002', 'a.b.contoh.xyz');
    ($this->buat)('Wld0003', 'contoh.xyzzy');

    AturanDomain::create(['pola_host' => '*.contoh.xyz', 'jenis' => 'blokir']);

    expect(LaporanPenyalahgunaan::pluck('kode_dilaporkan')->all())->toEqualCanonicalizing(['Wld0001', 'Wld0002']);
});

it('tidak memindai aturan izinkan atau aturan nonaktif', function () {
    ($this->buat)('Izn0001', 'contoh.com');

    AturanDomain::create(['pola_host' => 'contoh.com', 'jenis' => 'izinkan']);
    AturanDomain::create(['pola_host' => 'aman.com', 'jenis' => 'blokir', 'aktif' => false]);

    expect(LaporanPenyalahgunaan::count())->toBe(0);
});

it('menandai tautan yang kodenya melanggar slug terlarang baru dengan tiga cara cocok', function () {
    ($this->buat)('Resmi', 'a.com');
    ($this->buat)('resmi-baru', 'a.com');
    ($this->buat)('xxresmixx', 'a.com');
    ($this->buat)('Aman123', 'a.com');

    SlugTerlarang::create(['pola' => 'resmi', 'jenis' => 'cadangan_kelembagaan', 'cara_cocok' => 'persis']);
    expect(LaporanPenyalahgunaan::pluck('kode_dilaporkan')->all())->toBe(['Resmi']);

    SlugTerlarang::create(['pola' => 'resmi', 'jenis' => 'cadangan_kelembagaan', 'cara_cocok' => 'awalan']);
    expect(LaporanPenyalahgunaan::pluck('kode_dilaporkan')->all())->toEqualCanonicalizing(['Resmi', 'resmi-baru', 'Resmi']);

    SlugTerlarang::create(['pola' => 'resmi', 'jenis' => 'cadangan_kelembagaan', 'cara_cocok' => 'mengandung']);
    expect(LaporanPenyalahgunaan::where('kode_dilaporkan', 'xxresmixx')->count())->toBe(1)
        ->and(LaporanPenyalahgunaan::where('kode_dilaporkan', 'Aman123')->count())->toBe(0);
});

it('tidak membuat laporan ganda untuk aturan yang sama', function () {
    ($this->buat)('Dup0001', 'contoh.com');
    $aturan = AturanDomain::create(['pola_host' => 'contoh.com', 'jenis' => 'blokir']);

    (new PindaiUlangAturan('domain', $aturan->id))->handle();
    (new PindaiUlangAturan('domain', $aturan->id))->handle();

    expect(LaporanPenyalahgunaan::count())->toBe(1);
});

it('memindai ulang saat aturan diaktifkan atau diubah, bukan saat disimpan tanpa perubahan relevan', function () {
    Queue::fake();
    $aturan = AturanDomain::create(['pola_host' => 'contoh.com', 'jenis' => 'blokir', 'aktif' => false]);
    Queue::assertNothingPushed();

    $aturan->update(['aktif' => true]);
    Queue::assertPushed(PindaiUlangAturan::class, 1);

    $aturan->update(['alasan' => 'hanya ganti alasan']);
    Queue::assertPushed(PindaiUlangAturan::class, 1);

    $aturan->update(['pola_host' => 'contoh.net']);
    Queue::assertPushed(PindaiUlangAturan::class, 2);
});

it('mengantre job di antrean default dan tidak berjalan saat seeder', function () {
    Queue::fake();

    $this->seed(SlugTerlarangSeeder::class);
    $this->seed(AturanDomainSeeder::class);
    Queue::assertNothingPushed();

    AturanDomain::create(['pola_host' => 'baru.com', 'jenis' => 'blokir']);
    Queue::assertPushed(PindaiUlangAturan::class, fn ($j) => $j->queue === 'default');
});

it('mengabaikan entri prefiks unit otomatis', function () {
    ($this->buat)('pmat-seminar', 'a.com');
    SlugTerlarang::create(['pola' => 'pmat-', 'jenis' => 'cadangan_kelembagaan', 'cara_cocok' => 'awalan', 'keterangan' => 'prefiks-unit:xyz']);

    expect(LaporanPenyalahgunaan::count())->toBe(0);
});
