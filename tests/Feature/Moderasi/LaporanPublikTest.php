<?php

use App\Actions\Moderasi\TerimaLaporan;
use App\Enums\StatusTautan;
use App\Events\LaporanPenyalahgunaanDiterima;
use App\Models\LaporanPenyalahgunaan;
use App\Models\Pengaturan;
use App\Models\RiwayatStatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->pemilik = User::factory()->create();
    $this->tautan = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['kode' => 'Lapor01', 'url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle']);
    $this->kirim = fn (array $data = [], string $ip = '103.21.44.17') => $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/lapor', ['kode' => 'Lapor01', 'kategori' => 'judi', 'keterangan' => 'Mengarah ke situs judi.', 'website' => '', ...$data]);
});

it('menampilkan formulir dengan kode terisi dari tautan galat', function () {
    $this->get('/lapor?kode=Lapor01')->assertOk()->assertSee('Laporkan tautan bermasalah')->assertSee('value="Lapor01"', false)
        ->assertSee('Judi daring')->assertSee('Phishing');
});

it('menautkan dari halaman galat dan pratinjau ke formulir lapor', function () {
    $this->get('/TakAda12')->assertSee('/lapor?kode=TakAda12', false);
    $this->get('/Lapor01+')->assertSee('/lapor?kode=Lapor01', false);
});

it('menyimpan laporan tanpa IP utuh dan memicu event', function () {
    Event::fake([LaporanPenyalahgunaanDiterima::class]);

    ($this->kirim)()->assertRedirect(route('lapor.formulir'))->assertSessionHas('terkirim');

    $l = LaporanPenyalahgunaan::firstOrFail();
    expect($l->tautan_pendek_id)->toBe($this->tautan->id)
        ->and($l->kode_dilaporkan)->toBe('Lapor01')
        ->and($l->kategori->value)->toBe('judi')
        ->and($l->ip_hash)->toHaveLength(64)
        ->and($l->status->value)->toBe('baru');

    foreach (DB::select("select name from sqlite_master where type='table'") as $tabel) {
        foreach (DB::table($tabel->name)->get() as $baris) {
            expect(json_encode($baris))->not->toContain('103.21.44.17');
        }
    }
    Event::assertDispatched(LaporanPenyalahgunaanDiterima::class);
});

it('menerima URL pendek lengkap, kode+, dan menyimpan laporan untuk kode yang tidak ditemukan', function () {
    ($this->kirim)(['kode' => 'https://alias.test/Lapor01+', 'kategori' => 'spam'])->assertSessionHas('terkirim');
    ($this->kirim)(['kode' => 'KodeSalah', 'kategori' => 'spam'])->assertSessionHas('terkirim');

    $l = LaporanPenyalahgunaan::orderBy('id')->get();
    expect($l[0]->tautan_pendek_id)->toBe($this->tautan->id)->and($l[0]->kode_dilaporkan)->toBe('Lapor01')
        ->and($l[1]->tautan_pendek_id)->toBeNull()->and($l[1]->kode_dilaporkan)->toBe('KodeSalah');
});

it('memvalidasi masukan', function () {
    ($this->kirim)(['kategori' => 'tidak-ada'])->assertSessionHasErrors('kategori');
    ($this->kirim)(['kode' => ''])->assertSessionHasErrors('kode');
    ($this->kirim)(['email' => 'bukan-surel'])->assertSessionHasErrors('email');
    ($this->kirim)(['keterangan' => str_repeat('a', 2001)])->assertSessionHasErrors('keterangan');
    expect(LaporanPenyalahgunaan::count())->toBe(0);
});

it('membuang tag HTML dari keterangan', function () {
    ($this->kirim)(['keterangan' => 'Lihat <script>alert(1)</script> ini'])->assertSessionHas('terkirim');

    expect(LaporanPenyalahgunaan::firstOrFail()->keterangan)->not->toContain('<script>');
});

it('mengabaikan diam-diam bila honeypot terisi', function () {
    ($this->kirim)(['website' => 'http://spam'])->assertSessionHas('terkirim');

    expect(LaporanPenyalahgunaan::count())->toBe(0);
});

it('membatasi 5 laporan per jam per IP (429)', function () {
    foreach (range(1, 5) as $_) {
        ($this->kirim)()->assertRedirect();
    }

    ($this->kirim)()->assertStatus(429);
});

it('memblokir otomatis setelah 3 laporan judi dari ip_hash berbeda dan mencatat riwayat oleh sistem (BR-37)', function () {
    foreach (['103.21.44.1', '103.21.44.2'] as $ip) {
        ($this->kirim)([], $ip);
    }
    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Aktif);

    ($this->kirim)([], '103.21.44.3');

    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Diblokir)
        ->and($this->tautan->fresh()->alasan_status)->toBe(TerimaLaporan::ALASAN_OTOMATIS);
    $r = RiwayatStatusTautan::where('tautan_pendek_id', $this->tautan->id)->orderByDesc('id')->first();
    expect($r->oleh)->toBeNull()->and($r->ke_status)->toBe(StatusTautan::Diblokir);
    $this->get('/Lapor01')->assertStatus(410)->assertSee('melanggar ketentuan');
});

it('tidak memblokir bila tiga laporan berasal dari ip_hash yang sama', function () {
    foreach (range(1, 3) as $_) {
        ($this->kirim)([], '103.21.44.9');
    }

    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Aktif)->and(LaporanPenyalahgunaan::count())->toBe(3);
});

it('tidak pernah memblokir bila ambang 0', function () {
    Pengaturan::where('kunci', 'ambang_blokir_otomatis')->first()->update(['nilai' => '0']);

    foreach (range(1, 4) as $i) {
        ($this->kirim)([], "103.21.44.{$i}");
    }

    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Aktif);
});

it('menghitung hanya kategori phishing/malware/judi dalam 24 jam terakhir', function () {
    foreach (['103.21.44.1', '103.21.44.2'] as $ip) {
        ($this->kirim)(['kategori' => 'spam'], $ip);
    }
    ($this->kirim)(['kategori' => 'judi'], '103.21.44.3');
    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Aktif);

    // laporan lama (>24 jam) tidak dihitung
    LaporanPenyalahgunaan::query()->update(['created_at' => now()->subDays(2)]);
    ($this->kirim)(['kategori' => 'phishing'], '103.21.44.4');
    ($this->kirim)(['kategori' => 'malware'], '103.21.44.5');
    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Aktif);

    ($this->kirim)(['kategori' => 'judi'], '103.21.44.6');
    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Diblokir);
});

it('tidak memblokir lagi tautan yang sudah diblokir atau laporan untuk kode tak dikenal', function () {
    ($this->kirim)(['kode' => 'TakAda99'], '103.21.44.1');
    ($this->kirim)(['kode' => 'TakAda99'], '103.21.44.2');
    ($this->kirim)(['kode' => 'TakAda99'], '103.21.44.3');

    expect(TautanPendek::where('status', 'diblokir')->count())->toBe(0);
});

it('menyembunyikan ip_hash dan surel pelapor dari jejak audit', function () {
    ($this->kirim)(['email' => 'pelapor@contoh.com'])->assertSessionHas('terkirim');

    $log = Activity::where('subject_type', LaporanPenyalahgunaan::class)->first();
    expect(json_encode($log->attribute_changes))->not->toContain('ip_hash')->not->toContain('pelapor@contoh.com');
});
