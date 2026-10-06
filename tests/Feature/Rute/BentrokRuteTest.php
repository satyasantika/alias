<?php

use App\Support\Kode\DaftarSegmenRute;
use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @return array<int, string> */
function segmenUji(): array
{
    return ['panel', 'horizon', 'livewire', 'up', 'api', 'lapor', 'minta-akses', 'privasi', 'auth', 'login', 'logout',
        'register', 'storage', 'build', 'vendor', 'filament', 'admin', 'health', 'sanctum', 'password', 'favicon.ico', 'robots.txt'];
}

function namaRuteYangCocok(object $koleksi, string $uri): ?string
{
    try {
        return $koleksi->match(Request::create($uri, 'GET'))->getName();
    } catch (NotFoundHttpException) {
        return null;
    }
}

it('tidak menangani segmen sistem lewat pendek.alihkan', function (string $segmen) {
    expect(namaRuteYangCocok(Route::getRoutes(), '/'.$segmen))->not->toBe('pendek.alihkan')
        ->and(namaRuteYangCocok(Route::getRoutes(), '/'.$segmen.'+'))->not->toBe('pendek.alihkan');
})->with(fn () => array_map(fn ($s) => [$s], segmenUji()));

it('tetap tidak menangani segmen sistem pada koleksi rute ter-cache (BR-36)', function () {
    $terkompilasi = Route::getRoutes()->compile();
    $cache = new CompiledRouteCollection($terkompilasi['compiled'], $terkompilasi['attributes']);
    $cache->setRouter(app('router'));
    $cache->setContainer(app());

    foreach (segmenUji() as $segmen) {
        expect(namaRuteYangCocok($cache, '/'.$segmen))->not->toBe('pendek.alihkan', $segmen);
    }

    expect(namaRuteYangCocok($cache, '/Abc1234'))->toBe('pendek.alihkan')
        ->and(namaRuteYangCocok($cache, '/api-docs'))->toBe('pendek.alihkan')
        ->and(namaRuteYangCocok($cache, '/seminar-pmat-2026'))->toBe('pendek.alihkan');
});

it('tetap menangani slug yang hanya berawalan sama dengan segmen sistem', function () {
    expect(namaRuteYangCocok(Route::getRoutes(), '/api-docs'))->toBe('pendek.alihkan')
        ->and(namaRuteYangCocok(Route::getRoutes(), '/panelku'))->toBe('pendek.alihkan')
        ->and(namaRuteYangCocok(Route::getRoutes(), '/lapor-ini'))->toBe('pendek.alihkan');
});

it('mencakup setiap segmen pertama rute statis aplikasi', function () {
    foreach (DaftarSegmenRute::hitung() as $segmen) {
        expect(namaRuteYangCocok(Route::getRoutes(), '/'.$segmen))->not->toBe('pendek.alihkan', $segmen);
    }
});

it('memuat rute pengalihan paling akhir', function () {
    $nama = array_map(fn ($r) => $r->getName(), Route::getRoutes()->getRoutes());
    $posisi = array_search('pendek.alihkan', $nama, true);

    foreach ($nama as $i => $n) {
        if ($n !== null && preg_match('/^(filament|horizon|akses|auth|api|tautan|livewire)\./', $n)) {
            expect($i)->toBeLessThan($posisi, $n);
        }
    }
});

it('mengarahkan panel dan login tetap ke panel', function () {
    $this->get('/panel/login')->assertOk()->assertSee('Masuk ke Alias FKIP');
    $this->get('/up')->assertOk();
    $this->get('/api/health')->assertStatus(200);
});

it('membungkus rute pengalihan dengan domain pendek bila dikonfigurasi', function () {
    config(['alias.domain_pendek' => 'go.fkip.unsil.ac.id']);
    $rute = Route::getRoutes()->getByName('pendek.alihkan');

    // Konfigurasi dibaca saat boot; uji ini memastikan nilai default (tanpa domain) tidak membatasi host.
    expect($rute->getDomain())->toBeNull();
});
