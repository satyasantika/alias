<?php

use App\Models\TautanPendek;
use App\Models\User;

beforeEach(function () {
    $this->pemilik = User::factory()->create();
    $this->buat = fn (array $atribut = [], ?string $status = null) => TautanPendek::factory()->milikPribadi($this->pemilik)
        ->when($status, fn ($f) => $f->denganStatus($status))
        ->create(['url_tujuan' => 'https://forms.gle/abc?x=1', 'host_tujuan' => 'forms.gle', ...$atribut]);
});

it('mengalihkan tautan aktif dengan 302, Location benar, dan header BR-09', function () {
    ($this->buat)(['kode' => 'Aktif01']);

    $r = $this->get('/Aktif01');

    $r->assertStatus(302)->assertHeader('Location', 'https://forms.gle/abc?x=1');
    expect($r->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($r->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($r->headers->get('Referrer-Policy'))->toBe('unsafe-url')
        ->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($r->headers->getCookies())->toBe([])
        ->and($r->headers->has('Set-Cookie'))->toBeFalse();
});

it('mengalihkan HEAD tanpa cookie', function () {
    ($this->buat)(['kode' => 'Head001']);

    $r = $this->call('HEAD', '/Head001');

    expect($r->getStatusCode())->toBe(302)->and($r->headers->has('Set-Cookie'))->toBeFalse();
});

it('memakai kode redirect 301 bila diatur', function () {
    ($this->buat)(['kode' => 'Perm001', 'kode_status_redirect' => 301]);

    $this->get('/Perm001')->assertStatus(301);
});

it('menjawab 404 untuk kode yang tidak ada dengan halaman Indonesia noindex', function () {
    $r = $this->get('/TidakAda');

    $r->assertStatus(404)->assertSee('Tautan tidak ditemukan')->assertSee('Laporkan tautan ini')->assertSee('Ke beranda');
    expect($r->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')->and($r->headers->has('Set-Cookie'))->toBeFalse();
});

it('menyamarkan status menunggu persetujuan dan ditolak sebagai 404', function (string $status) {
    ($this->buat)(['kode' => 'Samar01'], $status);

    $this->get('/Samar01')->assertStatus(404)->assertSee('Tautan tidak ditemukan');
})->with(['menunggu_persetujuan', 'ditolak']);

it('menjawab 404 "belum aktif" untuk tautan terjadwal', function () {
    ($this->buat)(['kode' => 'Jadwal1', 'aktif_mulai' => now()->addDay()]);

    $this->get('/Jadwal1')->assertStatus(404)->assertSee('Tautan belum aktif');
});

it('menjawab 410 untuk kedaluwarsa, dinonaktifkan, terhapus, dan habis', function () {
    ($this->buat)(['kode' => 'Kedalu1', 'aktif_sampai' => now()->subMinute()]);
    ($this->buat)(['kode' => 'Nonakt1'], 'dinonaktifkan');
    ($this->buat)(['kode' => 'Hapus01'])->delete();
    ($this->buat)(['kode' => 'Habis01', 'batas_klik' => 3])->forceFill(['jumlah_klik' => 3])->saveQuietly();

    $this->get('/Kedalu1')->assertStatus(410)->assertSee('kedaluwarsa');
    $this->get('/Nonakt1')->assertStatus(410)->assertSee('dinonaktifkan');
    $this->get('/Hapus01')->assertStatus(410)->assertSee('dihapus');
    $this->get('/Habis01')->assertStatus(410)->assertSee('Kuota klik');
});

it('menjawab 410 dengan pesan pelanggaran untuk tautan diblokir tanpa membocorkan tujuan', function () {
    ($this->buat)(['kode' => 'Blokir1'], 'diblokir');

    $r = $this->get('/Blokir1');

    $r->assertStatus(410)->assertSee('melanggar ketentuan')->assertDontSee('forms.gle');
});

it('mencocokkan slug kustom tanpa peka huruf besar tetapi kode acak harus persis (BR-12)', function () {
    ($this->buat)(['kode' => 'seminar-pmat', 'kode_kustom' => true]);
    ($this->buat)(['kode' => 'abc1234', 'kode_kustom' => false]);

    $this->get('/Seminar-PMAT')->assertStatus(302);
    $this->get('/seminar-pmat')->assertStatus(302);
    $this->get('/ABC1234')->assertStatus(404);
    $this->get('/abc1234')->assertStatus(302);
});

it('membedakan kode acak yang hanya beda huruf besar kecil', function () {
    ($this->buat)(['kode' => 'AbCd123', 'url_tujuan' => 'https://forms.gle/satu']);
    ($this->buat)(['kode' => 'abcd123', 'url_tujuan' => 'https://forms.gle/dua']);

    $this->get('/AbCd123')->assertHeader('Location', 'https://forms.gle/satu');
    $this->get('/abcd123')->assertHeader('Location', 'https://forms.gle/dua');
});

it('tidak mengirim Set-Cookie dan tidak membuat sesi', function () {
    ($this->buat)(['kode' => 'Tanpa001']);

    $r = $this->get('/Tanpa001');

    expect($r->headers->getCookies())->toBe([]);
    $this->assertGuest();
});

it('mengirim HSTS hanya pada HTTPS', function () {
    ($this->buat)(['kode' => 'Hsts001']);

    expect($this->get('/Hsts001')->headers->has('Strict-Transport-Security'))->toBeFalse()
        ->and($this->get('https://alias.test/Hsts001')->headers->has('Strict-Transport-Security'))->toBeTrue();
});

it('menolak tujuan berisi CR/LF sebagai pertahanan berlapis', function () {
    ($this->buat)(['kode' => 'Crlf001'])->forceFill(['url_tujuan' => "https://forms.gle/a\r\nSet-Cookie: x=1"])->saveQuietly();

    $this->get('/Crlf001')->assertStatus(500);
});

it('membatasi 300 permintaan per menit per IP', function () {
    ($this->buat)(['kode' => 'Laju001']);

    foreach (range(1, 300) as $_) {
        $this->get('/Laju001')->assertStatus(302);
    }

    $this->get('/Laju001')->assertStatus(429);
});
