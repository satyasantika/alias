<?php

use App\Exceptions\UrlTujuanTidakValid;
use App\Models\AturanDomain;
use App\Models\Pengaturan;
use App\Rules\UrlTujuanValid;
use App\Support\Tujuan\NormalisasiUrl;
use App\Support\Tujuan\PemeriksaHostAman;
use App\Support\Tujuan\ResolverDns;
use App\Support\Tujuan\ValidatorUrlTujuan;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Spatie\Activitylog\Models\Activity;

/** DNS palsu: host → daftar IP; selain itu publik 93.184.216.34. */
function pasangDns(array $peta = []): void
{
    app()->instance(ResolverDns::class, new class($peta) extends ResolverDns
    {
        public function __construct(private array $peta) {}

        public function resolve(string $host): array
        {
            return $this->peta[$host] ?? ['93.184.216.34'];
        }
    });
}

beforeEach(function () {
    $this->seed([AturanDomainSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    config(['app.url' => 'http://alias.test:8018', 'alias.domain_pendek' => 'go.fkip.unsil.ac.id', 'alias.domain_panel' => 'alias.fkip.unsil.ac.id']);
    pasangDns();
});

it('menolak URL berbahaya dengan pesan spesifik', function (string $url, string $pesanMengandung) {
    $hasil = Validator::make(['u' => $url], ['u' => [new UrlTujuanValid]]);

    expect($hasil->fails())->toBeTrue()
        ->and($hasil->errors()->first('u'))->toContain($pesanMengandung);
})->with([
    'javascript' => ['javascript:alert(1)', 'http atau https'],
    'data' => ['data:text/html,<script>', 'http atau https'],
    'file' => ['file:///etc/passwd', 'http atau https'],
    'ftp' => ['ftp://x.com/a', 'http atau https'],
    'protocol relative' => ['//evil.com', 'lengkap'],
    'relatif' => ['/jalan/saja', 'lengkap'],
    'loopback v4' => ['http://127.0.0.1/', 'IP privat'],
    'loopback v6' => ['http://[::1]/', 'IP privat'],
    'link-local metadata' => ['http://169.254.169.254/latest/meta-data', 'IP privat'],
    'privat 10' => ['http://10.0.0.5/', 'IP privat'],
    'privat 192.168' => ['http://192.168.1.1/', 'IP privat'],
    'privat 172.16' => ['http://172.16.0.1/', 'IP privat'],
    'cgnat' => ['http://100.64.0.1/', 'IP privat'],
    'nol' => ['http://0.0.0.0/', 'IP privat'],
    'multicast' => ['http://224.0.0.1/', 'IP privat'],
    'v6 unique local' => ['http://[fc00::1]/', 'IP privat'],
    'v6 link-local' => ['http://[fe80::1]/', 'IP privat'],
    'v4-mapped loopback' => ['http://[::ffff:127.0.0.1]/', 'IP privat'],
    'IP desimal' => ['http://2130706433/', 'tidak standar'],
    'IP heksa' => ['http://0x7f.0.0.1/', 'tidak standar'],
    'userinfo' => ['http://user:pass@contoh.com/', 'nama pengguna'],
    'localhost' => ['http://localhost/', 'host lokal'],
    'sub localhost' => ['http://app.localhost/', 'host lokal'],
    'local' => ['http://printer.local/', 'host lokal'],
    'internal' => ['http://db.internal/', 'host lokal'],
    'host tanpa titik' => ['http://intranet/', 'nama domain lengkap'],
    'spasi' => ['http://contoh.com/a b', 'spasi'],
    'CRLF' => ["http://contoh.com/a\r\nSet-Cookie: x=1", 'spasi'],
    'backslash' => ['http://contoh.com\\@evil.com', 'garis miring terbalik'],
    'terlalu panjang' => ['https://contoh.com/'.str_repeat('a', 2100), 'terlalu panjang'],
    'bitly' => ['https://bit.ly/abc', 'diblokir'],
    'tinyurl' => ['https://tinyurl.com/x', 'diblokir'],
    'subdomain bitly tidak lolos wildcard' => ['https://s.id/x', 'diblokir'],
    'domain pendek sendiri' => ['https://go.fkip.unsil.ac.id/abc', 'Alias FKIP sendiri'],
    'domain panel sendiri' => ['https://alias.fkip.unsil.ac.id/panel', 'Alias FKIP sendiri'],
    'APP_URL sendiri' => ['http://alias.test:8018/abc', 'Alias FKIP sendiri'],
    'judi di host' => ['https://slot88gacor.com/', 'kata kunci'],
    'judi di path' => ['https://contoh.com/togel/hari-ini', 'kata kunci'],
]);

it('menerima URL sah termasuk forms.gle dan Drive', function (string $url) {
    $hasil = Validator::make(['u' => $url], ['u' => [new UrlTujuanValid]]);

    expect($hasil->passes())->toBeTrue($hasil->errors()->first('u'));
})->with([
    'forms.gle' => ['https://forms.gle/abc123'],
    'drive' => ['https://drive.google.com/file/d/x/view'],
    'docs' => ['https://docs.google.com/forms/d/e/xxx/viewform?usp=sf_link'],
    'port' => ['https://contoh.unsil.ac.id:8443/x'],
    'query & fragmen' => ['https://contoh.com/a?b=1&c=2#bagian'],
    'IDN' => ['https://bücher.example/halaman'],
    'subdomain unsil' => ['https://pmat.fkip.unsil.ac.id/seminar'],
]);

it('menormalisasi URL: huruf kecil, punycode, trim, enkode non-ASCII', function () {
    $n = app(NormalisasiUrl::class);

    expect($n->normalisasi('  HTTPS://Contoh.COM/Path?Q=1#Frag  '))->toBe(['url' => 'https://contoh.com/Path?Q=1#Frag', 'host' => 'contoh.com'])
        ->and($n->normalisasi('https://bücher.example/a')['host'])->toBe('xn--bcher-kva.example')
        ->and($n->normalisasi('https://contoh.com/é')['url'])->toBe('https://contoh.com/%C3%A9')
        ->and($n->normalisasi('https://contoh.com.')['host'])->toBe('contoh.com')
        ->and($n->normalisasi('http://[2001:4860:4860::8888]/x')['host'])->toBe('2001:4860:4860::8888');
});

it('mengembalikan hash SHA-256 URL ternormalisasi', function () {
    $hasil = app(ValidatorUrlTujuan::class)->validasi('HTTPS://Contoh.com/x');

    expect($hasil['hash'])->toBe(hash('sha256', 'https://contoh.com/x'))->and($hasil['host'])->toBe('contoh.com');
});

it('menolak host yang me-resolve ke IP privat, kecuali *.unsil.ac.id', function () {
    pasangDns(['rahasia.contoh.com' => ['10.1.2.3'], 'internal.unsil.ac.id' => ['10.9.9.9'], 'campur.contoh.com' => ['93.184.216.34', '192.168.0.9'], 'v6.contoh.com' => ['fd00::5']]);

    expect(fn () => app(ValidatorUrlTujuan::class)->validasi('https://rahasia.contoh.com/'))->toThrow(UrlTujuanTidakValid::class, 'jaringan internal')
        ->and(fn () => app(ValidatorUrlTujuan::class)->validasi('https://campur.contoh.com/'))->toThrow(UrlTujuanTidakValid::class)
        ->and(fn () => app(ValidatorUrlTujuan::class)->validasi('https://v6.contoh.com/'))->toThrow(UrlTujuanTidakValid::class)
        ->and(app(ValidatorUrlTujuan::class)->validasi('https://internal.unsil.ac.id/portal')['host'])->toBe('internal.unsil.ac.id');
});

it('hanya memberi peringatan saat DNS gagal', function () {
    pasangDns(['mati.contoh.com' => []]);

    $hasil = app(ValidatorUrlTujuan::class)->validasi('https://mati.contoh.com/x');

    expect($hasil['peringatan'])->toHaveCount(1);
});

it('menegakkan mode daftar putih', function () {
    Pengaturan::where('kunci', 'mode_domain')->first()->update(['nilai' => 'daftar_putih']);

    expect(app(ValidatorUrlTujuan::class)->validasi('https://forms.gle/x')['host'])->toBe('forms.gle')
        ->and(fn () => app(ValidatorUrlTujuan::class)->validasi('https://situs-lain.com/x'))->toThrow(UrlTujuanTidakValid::class, 'daftar domain');

    // blokir tetap didahulukan
    AturanDomain::where('pola_host', 'forms.gle')->first()->update(['jenis' => 'blokir']);
    expect(fn () => app(ValidatorUrlTujuan::class)->validasi('https://forms.gle/x'))->toThrow(UrlTujuanTidakValid::class);
});

it('mendukung pola wildcard host dan subdomain', function () {
    AturanDomain::create(['pola_host' => '*.xyz', 'jenis' => 'blokir']);

    expect(fn () => app(ValidatorUrlTujuan::class)->validasi('https://situs.xyz/'))->toThrow(UrlTujuanTidakValid::class)
        ->and(fn () => app(ValidatorUrlTujuan::class)->validasi('https://a.b.xyz/'))->toThrow(UrlTujuanTidakValid::class)
        ->and(app(ValidatorUrlTujuan::class)->validasi('https://xyzzy.com/')['host'])->toBe('xyzzy.com');
});

it('mencatat percobaan kata kunci judi di log aktivitas tanpa URL lengkap', function () {
    Validator::make(['u' => 'https://contoh.com/maxwin/promo?rahasia=1'], ['u' => [new UrlTujuanValid]])->fails();

    $log = Activity::query()->where('event', 'percobaan-mencurigakan')->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['kata_kunci'])->toBe('maxwin')
        ->and(json_encode($log->properties))->not->toContain('rahasia');
});

it('mengklasifikasi IP dengan benar', function (string $ip, bool $aman) {
    expect(app(PemeriksaHostAman::class)->ipAman($ip))->toBe($aman);
})->with([
    ['93.184.216.34', true], ['8.8.8.8', true], ['2001:4860:4860::8888', true], ['::ffff:8.8.8.8', true],
    ['127.0.0.1', false], ['10.255.255.255', false], ['172.31.0.1', false], ['172.32.0.1', true], ['192.168.5.5', false],
    ['169.254.0.1', false], ['100.127.255.255', false], ['100.128.0.1', true], ['255.255.255.255', false], ['::1', false],
    ['::', false], ['fe80::1', false], ['fd12:3456::1', false], ['::ffff:10.0.0.1', false], ['203.0.113.5', false],
]);
