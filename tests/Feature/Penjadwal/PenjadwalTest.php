<?php

use App\Enums\Peran;
use App\Enums\StatusPermintaanAkses;
use App\Enums\StatusTautan;
use App\Models\KunjunganTautan;
use App\Models\LogLogin;
use App\Models\PermintaanAkses;
use App\Models\RekapKunjunganDimensi;
use App\Models\RekapKunjunganHarian;
use App\Models\TautanPendek;
use App\Models\User;
use App\Notifications\RingkasanTautanYatim;
use App\Notifications\TautanAkanKedaluwarsa;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    $this->pemilik = User::factory()->create();
    $this->tautan = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['kode' => 'Rekap01']);
    $this->kunjungan = fn (array $a = []) => KunjunganTautan::create([
        'tautan_pendek_id' => $this->tautan->id, 'dikunjungi_pada' => now()->subDay()->setTime(10, 0), 'ip_anonim' => '103.21.44.0',
        'ip_hash' => str_repeat('a', 64), 'peramban' => 'Chrome', 'os' => 'Windows', 'jenis_perangkat' => 'desktop',
        'perujuk_host' => null, 'bot' => false, ...$a,
    ]);
});

it('merekap kunjungan kemarin: klik, unik per ip_hash, bot terpisah', function () {
    ($this->kunjungan)();
    ($this->kunjungan)();                                   // ip_hash sama → tidak menambah unik
    ($this->kunjungan)(['ip_hash' => str_repeat('b', 64), 'peramban' => 'Firefox', 'perujuk_host' => 'www.facebook.com']);
    ($this->kunjungan)(['bot' => true, 'jenis_perangkat' => 'bot', 'ip_hash' => str_repeat('c', 64)]);
    ($this->kunjungan)(['dikunjungi_pada' => now()->subDays(3)]);       // hari lain

    $this->artisan('alias:rekap-kunjungan')->assertSuccessful();

    $r = RekapKunjunganHarian::where('tautan_pendek_id', $this->tautan->id)->get();
    expect($r)->toHaveCount(1)
        ->and($r[0]->tanggal->format('Y-m-d'))->toBe(now()->subDay()->format('Y-m-d'))
        ->and($r[0]->jumlah_klik)->toBe(3)
        ->and($r[0]->jumlah_pengunjung_unik)->toBe(2)
        ->and($r[0]->jumlah_bot)->toBe(1);

    $peramban = RekapKunjunganDimensi::where('dimensi', 'peramban')->pluck('jumlah', 'nilai')->all();
    $perujuk = RekapKunjunganDimensi::where('dimensi', 'perujuk_host')->pluck('jumlah', 'nilai')->all();
    expect($peramban)->toBe(['Chrome' => 2, 'Firefox' => 1])
        ->and($perujuk)->toBe(['(langsung)' => 2, 'www.facebook.com' => 1]);
});

it('menghasilkan angka sama saat rekap dijalankan dua kali (idempoten) dan memperbarui data baru', function () {
    ($this->kunjungan)();
    ($this->kunjungan)(['ip_hash' => str_repeat('b', 64)]);

    $this->artisan('alias:rekap-kunjungan')->assertSuccessful();
    $pertama = [RekapKunjunganHarian::count(), RekapKunjunganDimensi::count(), RekapKunjunganHarian::first()->jumlah_klik];
    $this->artisan('alias:rekap-kunjungan')->assertSuccessful();

    expect([RekapKunjunganHarian::count(), RekapKunjunganDimensi::count(), RekapKunjunganHarian::first()->jumlah_klik])->toBe($pertama);

    ($this->kunjungan)(['ip_hash' => str_repeat('d', 64)]);
    $this->artisan('alias:rekap-kunjungan')->assertSuccessful();
    expect(RekapKunjunganHarian::count())->toBe(1)->and(RekapKunjunganHarian::first()->jumlah_klik)->toBe(3)
        ->and(RekapKunjunganDimensi::where('dimensi', 'peramban')->sum('jumlah'))->toBe(3);
});

it('menerima opsi --tanggal dan menolak rekap bersamaan lewat lock', function () {
    ($this->kunjungan)(['dikunjungi_pada' => '2026-05-01 09:00:00']);

    $this->artisan('alias:rekap-kunjungan', ['--tanggal' => '2026-05-01'])->expectsOutput('Rekap 2026-05-01: 1 tautan.')->assertSuccessful();

    $kunci = Cache::lock('agregasi-kunjungan:2026-05-01', 600);
    expect($kunci->get())->toBeTrue();
    $this->artisan('alias:rekap-kunjungan', ['--tanggal' => '2026-05-01'])->assertFailed();
    $kunci->release();
});

it('meleburkan ekor panjang dimensi (> 20 nilai/hari) ke (lainnya)', function () {
    foreach (range(1, 25) as $i) {
        ($this->kunjungan)(['ip_hash' => hash('sha256', (string) $i), 'perujuk_host' => "situs{$i}.com"]);
    }

    $this->artisan('alias:rekap-kunjungan')->assertSuccessful();

    $perujuk = RekapKunjunganDimensi::where('dimensi', 'perujuk_host')->pluck('jumlah', 'nilai');
    expect($perujuk)->toHaveCount(21)->and($perujuk['(lainnya)'])->toBe(5);
});

it('memangkas hanya tanggal yang sudah direkap dan sesuai retensi manusia/bot', function () {
    $lama = now()->subMonths(13)->setTime(10, 0);
    $botLama = now()->subDays(40)->setTime(10, 0);
    ($this->kunjungan)(['dikunjungi_pada' => $lama]);                       // manusia lama, belum direkap
    ($this->kunjungan)(['dikunjungi_pada' => $botLama, 'bot' => true]);      // bot lama, belum direkap
    ($this->kunjungan)(['dikunjungi_pada' => now()->subDays(5)]);            // masih baru

    $this->artisan('alias:pangkas-kunjungan')->assertSuccessful();
    expect(KunjunganTautan::count())->toBe(3);                               // belum direkap → tidak dihapus

    $this->artisan('alias:rekap-kunjungan', ['--tanggal' => $lama->format('Y-m-d')])->assertSuccessful();
    $this->artisan('alias:pangkas-kunjungan')->assertSuccessful();
    expect(KunjunganTautan::count())->toBe(2)->and(KunjunganTautan::where('bot', true)->count())->toBe(1);

    $this->artisan('alias:rekap-kunjungan', ['--tanggal' => $botLama->format('Y-m-d')])->assertSuccessful();
    $this->artisan('alias:pangkas-kunjungan')->assertSuccessful();
    expect(KunjunganTautan::count())->toBe(1)->and(KunjunganTautan::first()->bot)->toBeFalse();
    expect(RekapKunjunganHarian::count())->toBe(2);                          // rekap tetap tersimpan
});

it('menolak otomatis slug menunggu 15 hari dan mengedaluwarsakan permintaan akses lama', function () {
    $menunggu = TautanPendek::factory()->milikPribadi($this->pemilik)->denganStatus('menunggu_persetujuan')->create(['kode' => 'slug-lama']);
    $baru = TautanPendek::factory()->milikPribadi($this->pemilik)->denganStatus('menunggu_persetujuan')->create(['kode' => 'slug-baru']);
    $menunggu->forceFill(['created_at' => now()->subDays(15)])->saveQuietly();
    $baruSaja = fn (string $email, StatusPermintaanAkses $s, int $hari) => tap(PermintaanAkses::create([
        'nama' => 'X', 'email' => $email, 'alasan' => 'x', 'ip_hash' => str_repeat('a', 64), 'status' => $s,
    ]), fn ($p) => $p->forceFill(['created_at' => now()->subDays($hari)])->saveQuietly());
    $lama = $baruSaja('a@unsil.ac.id', StatusPermintaanAkses::MenungguVerifikasiSurel, 8);
    $segar = $baruSaja('b@unsil.ac.id', StatusPermintaanAkses::MenungguVerifikasiSurel, 2);
    $menungguAdmin = $baruSaja('c@unsil.ac.id', StatusPermintaanAkses::Menunggu, 30);

    $this->artisan('alias:tolak-kedaluwarsa')->expectsOutput('1 slug ditolak, 1 permintaan akses kedaluwarsa.')->assertSuccessful();

    expect($menunggu->fresh()->status)->toBe(StatusTautan::Ditolak)->and($menunggu->fresh()->alasan_status)->toBe('Kedaluwarsa tanpa keputusan')
        ->and($baru->fresh()->status)->toBe(StatusTautan::MenungguPersetujuan)
        ->and($lama->fresh()->status)->toBe(StatusPermintaanAkses::Kedaluwarsa)
        ->and($segar->fresh()->status)->toBe(StatusPermintaanAkses::MenungguVerifikasiSurel)
        ->and($menungguAdmin->fresh()->status)->toBe(StatusPermintaanAkses::Menunggu);
});

it('mengingatkan pemilik sekali untuk tautan yang kedaluwarsa dalam 7 hari', function () {
    Notification::fake();
    $segera = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['aktif_sampai' => now()->addDays(3)]);
    TautanPendek::factory()->milikPribadi($this->pemilik)->create(['aktif_sampai' => now()->addDays(30)]);
    TautanPendek::factory()->milikPribadi($this->pemilik)->create();

    $this->artisan('alias:ingatkan-kedaluwarsa')->assertSuccessful();
    $this->artisan('alias:ingatkan-kedaluwarsa')->assertSuccessful();

    Notification::assertSentToTimes($this->pemilik, TautanAkanKedaluwarsa::class, 1);
    Notification::assertSentTo($this->pemilik, TautanAkanKedaluwarsa::class, fn ($n) => $n->tautan->is($segera));
});

it('melaporkan tautan yatim ke admin hanya bila ada', function () {
    Notification::fake();
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);

    $this->artisan('alias:laporan-yatim')->expectsOutput('Tidak ada tautan yatim.')->assertSuccessful();
    Notification::assertNothingSent();

    $yatim = User::factory()->create(['aktif' => false]);
    $t = TautanPendek::factory()->milikPribadi($yatim)->create(['kode' => 'Yatim001']);
    TautanPendek::factory()->milikPribadi($yatim)->denganStatus('dinonaktifkan')->create();      // bukan aktif → tidak dihitung

    $this->artisan('alias:laporan-yatim')->assertSuccessful();

    Notification::assertSentTo($admin, RingkasanTautanYatim::class, fn ($n) => $n->total === 1 && $n->daftar[0]['kode'] === 'Yatim001');
});

it('menghapus log login lebih dari 90 hari', function () {
    $buat = fn (int $hari) => LogLogin::create(['email' => 'a@unsil.ac.id', 'peristiwa' => 'berhasil', 'ip' => '1.2.3.4', 'created_at' => now()->subDays($hari)]);
    $buat(120);
    $buat(91);
    $baru = $buat(10);

    $this->artisan('alias:pangkas-log-login')->expectsOutput('2 log login dihapus.')->assertSuccessful();

    expect(LogLogin::pluck('id')->all())->toBe([$baru->id]);
});

it('membersihkan berkas tmp > 24 jam dan mempertahankan .gitignore', function () {
    $folder = storage_path('app/tmp');
    File::ensureDirectoryExists($folder);
    File::put("{$folder}/lama.csv", 'x');
    File::put("{$folder}/baru.csv", 'x');
    touch("{$folder}/lama.csv", now()->subHours(25)->getTimestamp());

    $this->artisan('alias:bersihkan-tmp')->expectsOutput('1 berkas sementara dihapus.')->assertSuccessful();

    expect(File::exists("{$folder}/lama.csv"))->toBeFalse()->and(File::exists("{$folder}/baru.csv"))->toBeTrue()->and(File::exists("{$folder}/.gitignore"))->toBeTrue();
    File::delete("{$folder}/baru.csv");
});

it('memuat seluruh perintah terjadwal dengan cron, zona waktu, dan pengaman yang benar', function () {
    $acara = collect(app(Schedule::class)->events())->mapWithKeys(fn (Event $e) => [
        trim(str_replace([PHP_BINARY, "'artisan'", 'artisan', "'"], '', $e->command)) => $e,
    ]);

    $diharapkan = [
        'horizon:snapshot' => '*/5 * * * *',
        'alias:rekap-kunjungan' => '20 0 * * *',
        'alias:pangkas-kunjungan' => '10 1 * * *',
        'alias:pangkas-log-login' => '30 1 * * *',
        'alias:tolak-kedaluwarsa' => '0 6 * * *',
        'alias:ingatkan-kedaluwarsa' => '5 7 * * *',
        'alias:periksa-tujuan' => '15 2 * * 0',
        'alias:laporan-yatim' => '10 7 * * 1',
        'alias:bersihkan-tmp' => '0 * * * *',
        'activitylog:clean' => '0 0 1 * *',
    ];

    foreach ($diharapkan as $perintah => $cron) {
        expect($acara->has($perintah))->toBeTrue("Perintah {$perintah} tidak terjadwal");
        $e = $acara[$perintah];
        expect($e->expression)->toBe($cron, $perintah)
            ->and($e->timezone)->toBe('Asia/Jakarta')
            ->and($e->withoutOverlapping)->toBeTrue()
            ->and($e->onOneServer)->toBeTrue();
    }
});
