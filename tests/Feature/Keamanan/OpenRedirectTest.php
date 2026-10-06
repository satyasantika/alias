<?php

use App\Models\TautanPendek;
use App\Models\User;

beforeEach(function () {
    $this->t = TautanPendek::factory()->milikPribadi(User::factory()->create())
        ->create(['kode' => 'Open001', 'url_tujuan' => 'https://forms.gle/aman', 'host_tujuan' => 'forms.gle']);
});

it('tidak pernah mengalihkan ke URL dari parameter request', function (string $query) {
    $r = $this->get('/Open001?'.$query);

    expect($r->getStatusCode())->toBe(302)->and($r->headers->get('Location'))->toBe('https://forms.gle/aman');
})->with([
    'url' => ['url=https://evil.com'],
    'next' => ['next=//evil.com'],
    'redirect' => ['redirect=http://evil.com'],
    'return' => ['return_to=javascript:alert(1)'],
    'campur' => ['a=1&url=https://evil.com&to=//evil.com'],
]);

it('hanya menambah query (bukan host) saat teruskan_query aktif', function () {
    $this->t->forceFill(['teruskan_query' => true])->saveQuietly();

    $lokasi = $this->get('/Open001?url=https://evil.com&u=//evil.com')->headers->get('Location');

    expect(parse_url($lokasi, PHP_URL_HOST))->toBe('forms.gle')->and(parse_url($lokasi, PHP_URL_SCHEME))->toBe('https');
});

it('header Location tidak pernah memuat CR atau LF walau query berisi injeksi header', function () {
    $this->t->forceFill(['teruskan_query' => true])->saveQuietly();

    $r = $this->get('/Open001?a=%0d%0aSet-Cookie:%20x=1&b=%0aLocation:%20https://evil.com');

    $lokasi = (string) $r->headers->get('Location');
    expect($r->getStatusCode())->toBe(302)
        ->and($lokasi)->not->toContain("\r")->not->toContain("\n")
        ->and(parse_url($lokasi, PHP_URL_HOST))->toBe('forms.gle')
        ->and($r->headers->has('Set-Cookie'))->toBeFalse();
});

it('tidak terpengaruh header Host atau X-Forwarded-Host yang dimanipulasi', function () {
    $r = $this->withHeaders(['Host' => 'evil.com', 'X-Forwarded-Host' => 'evil.com'])->get('/Open001');

    expect($r->headers->get('Location'))->toBe('https://forms.gle/aman');
});

it('menolak kode berisi karakter berbahaya sebelum mencapai pengalihan', function (string $kode) {
    expect($this->get('/'.$kode)->getStatusCode())->toBeIn([404]);
})->with(['%0d%0aX', 'a%2F..%2Fb', '..%2F..%2Fetc', "a'b", 'a b']);
