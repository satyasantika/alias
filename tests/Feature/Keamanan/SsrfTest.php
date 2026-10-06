<?php

use App\Exceptions\UrlTujuanTidakValid;
use App\Models\TautanPendek;
use App\Models\User;
use App\Support\Tujuan\PemeriksaTujuan;
use App\Support\Tujuan\ResolverDns;
use App\Support\Tujuan\ValidatorUrlTujuan;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

dataset('tujuan-berbahaya', [
    'javascript' => 'javascript:alert(1)', 'data' => 'data:text/html,x', 'file' => 'file:///etc/passwd', 'ftp' => 'ftp://x.com/',
    'loopback' => 'http://127.0.0.1/', 'loopback6' => 'http://[::1]/', 'metadata' => 'http://169.254.169.254/latest/meta-data/',
    'privat10' => 'http://10.0.0.5/', 'privat192' => 'http://192.168.0.1/', 'privat172' => 'http://172.16.0.1/', 'cgnat' => 'http://100.64.0.1/',
    'desimal' => 'http://2130706433/', 'heksa' => 'http://0x7f.0.0.1/', 'octal' => 'http://0177.0.0.1/', 'userinfo' => 'http://u:p@x.com/',
    'localhost' => 'http://localhost/', 'internal' => 'http://svc.internal/', 'tanpaTitik' => 'http://intranet/',
    'mapped' => 'http://[::ffff:127.0.0.1]/', 'ula' => 'http://[fc00::1]/', 'bitly' => 'https://bit.ly/x', 'judi' => 'https://slot88gacor.com/',
]);

beforeEach(function () {
    $this->seed([AturanDomainSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    app()->instance(ResolverDns::class, new class extends ResolverDns
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
});

it('menolak seluruh tujuan berbahaya pada validasi saat disimpan', function (string $url) {
    expect(fn () => app(ValidatorUrlTujuan::class)->validasi($url))->toThrow(UrlTujuanTidakValid::class);
})->with('tujuan-berbahaya');

it('tidak pernah melakukan permintaan HTTP ke tujuan berbahaya pada pemeriksaan kesehatan', function (string $url) {
    Http::fake();

    $hasil = app(PemeriksaTujuan::class)->periksa($url);

    expect($hasil->berhasil())->toBeFalse();
    Http::assertNothingSent();
})->with('tujuan-berbahaya');

it('menghentikan redirect berantai yang berakhir di jaringan internal', function (string $internal) {
    Http::fake(fn (Request $r) => str_contains($r->url(), 'awal.contoh.com')
        ? Http::response('', 302, ['Location' => $internal])
        : Http::response('', 200));

    $hasil = app(PemeriksaTujuan::class)->periksa('https://awal.contoh.com/x');

    expect($hasil->berhasil())->toBeFalse();
    Http::assertSentCount(1);
})->with(['http://127.0.0.1/', 'http://169.254.169.254/', 'http://10.1.1.1/', 'http://[::1]/', 'http://localhost/']);

it('memaku koneksi ke IP hasil resolusi yang sudah diperiksa (anti DNS-rebinding)', function () {
    $dipaku = null;
    Http::fake(function (Request $r) use (&$dipaku) {
        return Http::response('', 200);
    });

    $hasil = app(PemeriksaTujuan::class)->periksa('https://tujuan.contoh.com/x');

    expect($hasil->berhasil())->toBeTrue();
    $sumber = file_get_contents(app_path('Support/Tujuan/PemeriksaTujuan.php'));
    expect($sumber)->toContain('CURLOPT_RESOLVE');
});

it('tetap mengalihkan tautan lama tanpa melakukan permintaan ke tujuannya (pengalihan tidak memeriksa tujuan)', function () {
    Http::fake();
    $t = TautanPendek::factory()->milikPribadi(User::factory()->create())->create(['kode' => 'Ssrf001', 'url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle']);

    $this->get('/Ssrf001')->assertStatus(302);

    Http::assertNothingSent();
});
