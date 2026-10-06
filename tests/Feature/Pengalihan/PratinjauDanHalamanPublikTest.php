<?php

use App\Models\Pengaturan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->pemilik = User::factory()->create();
    $this->buat = fn (array $atribut = [], ?string $status = null) => TautanPendek::factory()->milikPribadi($this->pemilik)
        ->when($status, fn ($f) => $f->denganStatus($status))
        ->create(['url_tujuan' => 'https://forms.gle/rahasia?x=1', 'host_tujuan' => 'forms.gle', 'judul' => 'Formulir Seminar', ...$atribut]);
});

it('menampilkan pratinjau lengkap tanpa mencatat atau mengonsumsi klik', function () {
    Queue::fake();
    $t = ($this->buat)(['kode' => 'Prev001', 'batas_klik' => 1, 'sekali_pakai' => true]);

    $r = $this->get('/Prev001+');

    $r->assertOk()
        ->assertSee('Formulir Seminar')
        ->assertSee('forms.gle')
        ->assertSee('https://forms.gle/rahasia?x=1')
        ->assertSee('Pribadi')
        ->assertSee('Lanjutkan')
        ->assertSee('Laporkan tautan ini')
        ->assertSee('data:image/svg+xml;base64,', false)
        ->assertHeader('X-Frame-Options', 'DENY');
    expect($r->headers->has('Set-Cookie'))->toBeFalse()
        ->and($r->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($t->fresh()->jumlah_klik)->toBe(0)
        ->and($t->fresh()->dipakai_pada)->toBeNull();
    Queue::assertNothingPushed();

    // masih dapat dipakai setelah pratinjau
    $this->get('/Prev001')->assertStatus(302);
});

it('menampilkan nama unit sebagai pemilik', function () {
    $unit = Unit::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'jenis' => 'prodi']);
    TautanPendek::factory()->milikUnit($unit)->create(['kode' => 'Unit001', 'url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle']);

    $this->get('/Unit001+')->assertOk()->assertSee('Pendidikan Matematika');
});

it('tidak menampilkan tujuan untuk tautan diblokir tetapi tetap memberi pesan', function () {
    ($this->buat)(['kode' => 'Blok001'], 'diblokir');

    $this->get('/Blok001+')->assertOk()
        ->assertSee('melanggar ketentuan')
        ->assertDontSee('forms.gle')
        ->assertDontSee('rahasia')
        ->assertDontSee('Lanjutkan');
});

it('menampilkan tujuan pada tautan dinonaktifkan tetapi tanpa tombol lanjutkan', function () {
    ($this->buat)(['kode' => 'Nona001'], 'dinonaktifkan');

    $this->get('/Nona001+')->assertOk()->assertSee('forms.gle')->assertDontSee('Lanjutkan');
});

it('menyembunyikan tautan menunggu atau ditolak dan kode yang tidak ada', function (string $status) {
    ($this->buat)(['kode' => 'Samr001'], $status);

    $this->get('/Samr001+')->assertStatus(404)->assertDontSee('forms.gle');
    $this->get('/TakAda+')->assertStatus(404);
})->with(['menunggu_persetujuan', 'ditolak']);

it('tidak menampilkan tujuan tautan terhapus', function () {
    ($this->buat)(['kode' => 'Del0001'])->delete();

    $this->get('/Del0001+')->assertOk()->assertSee('dihapus')->assertDontSee('rahasia');
});

it('meng-escape judul berbahaya pada pratinjau', function () {
    ($this->buat)(['kode' => 'Xss0001', 'judul' => '<script>alert(1)</script>']);

    $this->get('/Xss0001+')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});

it('membatasi pratinjau 60 per menit per IP', function () {
    ($this->buat)(['kode' => 'Laju002']);

    foreach (range(1, 60) as $_) {
        $this->get('/Laju002+')->assertOk();
    }

    $this->get('/Laju002+')->assertStatus(429);
});

it('menampilkan beranda publik', function () {
    $this->get('/')->assertOk()->assertSee('Alias FKIP')->assertSee('Masuk')->assertSee('Laporkan')->assertSee('Privasi');
});

it('menampilkan halaman privasi dengan retensi dari pengaturan', function () {
    $this->get('/privasi')->assertOk()
        ->assertSee('12 bulan')->assertSee('30 hari')->assertSee('dianonimkan')->assertSee('Kontak');

    Pengaturan::where('kunci', 'retensi_kunjungan_bulan')->first()->update(['nilai' => '6']);
    $this->get('/privasi')->assertSee('6 bulan');
});

it('meng-escape teks privasi dari pengaturan', function () {
    Pengaturan::where('kunci', 'teks_pemberitahuan_privasi')->first()->update(['nilai' => '<img src=x onerror=alert(1)>']);

    $this->get('/privasi')->assertDontSee('<img src=x', false)->assertSee('&lt;img src=x', false);
});

it('tidak memakai {!! !!} untuk data pengguna pada tampilan publik', function () {
    foreach (glob(resource_path('views/{pendek,akses}/*.blade.php'), GLOB_BRACE) as $berkas) {
        expect(file_get_contents($berkas))->not->toContain('{!!');
    }
    expect(file_get_contents(resource_path('views/privasi.blade.php')))->not->toContain('{!!')
        ->and(file_get_contents(resource_path('views/beranda.blade.php')))->not->toContain('{!!');
});
