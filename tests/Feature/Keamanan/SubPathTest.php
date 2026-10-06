<?php

use App\Models\TautanPendek;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

const ROOT = 'https://supportfkip.unsil.ac.id/alias';
const HOST = 'https://supportfkip.unsil.ac.id';   // proxy front membuang /alias sebelum sampai ke aplikasi

beforeEach(function () {
    config(['app.url' => ROOT, 'alias.paksa_sub_path' => true, 'alias.domain_pendek' => null, 'alias.domain_panel' => null]);
    app()->getProvider(AppServiceProvider::class)->boot();
});

afterEach(fn () => URL::forceRootUrl(null));

it('membangkitkan URL beranda, panel, dan Livewire di bawah /alias', function () {
    $html = $this->get(HOST.'/panel/login')->assertOk()->getContent();

    expect($html)->toContain(ROOT.'/livewire')
        ->and(preg_match_all('#https?://[^"\'\s<>]+#', $html, $m) ? collect($m[0])->filter(fn ($u) => str_contains($u, 'supportfkip')) : collect())->not->toBeEmpty();

    foreach ($m[0] as $url) {
        if (str_contains($url, 'localhost') || str_starts_with($url, 'http://alias')) {
            fail("URL mengabaikan sub-path: {$url}");
        }
    }
    expect(route('beranda'))->toBe(ROOT)->and(url('/lapor'))->toBe(ROOT.'/lapor');
});

it('menautkan halaman publik ke URL di bawah /alias', function () {
    $this->get(HOST.'/')->assertOk()->assertSee(ROOT.'/panel/login', false)->assertSee(ROOT.'/minta-akses', false)->assertSee(ROOT.'/lapor', false);
    $this->get(HOST.'/lapor')->assertOk()->assertSee('action="'.ROOT.'/lapor"', false);
    $this->get(HOST.'/minta-akses')->assertOk()->assertSee('action="'.ROOT.'/minta-akses"', false);
});

it('membentuk URL pendek, pratinjau, dan QR dari APP_URL beserta sub-path', function () {
    $t = TautanPendek::factory()->milikPribadi(User::factory()->create())->create(['kode' => 'Sub0001', 'url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle']);

    expect($t->url_pendek)->toBe(ROOT.'/Sub0001');
    $this->get(HOST.'/Sub0001+')->assertOk()->assertSee(ROOT.'/Sub0001', false);
    $this->get(HOST.'/Sub0001')->assertStatus(302)->assertHeader('Location', 'https://forms.gle/x');
});

it('mengalihkan tamu ke halaman login di bawah /alias', function () {
    $this->get(HOST.'/panel')->assertRedirect(ROOT.'/panel/login');
});

it('tidak mengubah URL ketika APP_URL tanpa sub-path', function () {
    URL::forceRootUrl(null);
    config(['app.url' => 'https://alias.test']);
    app()->getProvider(AppServiceProvider::class)->boot();

    expect(url('/lapor'))->not->toContain('/alias/');
});

it('mereservasi segmen panduan dan membuang awalan di public/index.php', function () {
    expect(config('alias.segmen_sistem'))->toContain('panduan');

    $sumber = file_get_contents(public_path('index.php'));
    expect($sumber)->toContain('ALIAS_BASE_PATH')->toContain('substr($uri, strlen($awalan))');
});

it('menyiapkan konfigurasi produksi untuk https://supportfkip.unsil.ac.id/alias', function () {
    $env = file_get_contents(base_path('.env.production.example'));
    $nginx = file_get_contents(base_path('docker/nginx/produksi.conf.template'));

    expect($env)->toContain('APP_URL=https://supportfkip.unsil.ac.id/alias')->toContain('ALIAS_BASE_PATH=/alias')->toContain('SESSION_PATH=/alias')
        ->and($nginx)->toContain('NGINX_BASE_PATH');
});
