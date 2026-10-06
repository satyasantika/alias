<?php

use App\Http\Middleware\HeaderKeamananPublik;
use App\Models\TautanPendek;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->t = TautanPendek::factory()->milikPribadi(User::factory()->create())
        ->create(['kode' => 'Head001', 'url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle']);
});

it('memberi X-Frame-Options DENY dan CSP pada pratinjau dan seluruh halaman publik', function (string $path) {
    $r = $this->get($path);

    expect($r->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($r->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")->toContain("frame-ancestors 'none'")->toContain("script-src 'none'")
        ->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff');
})->with(['/Head001+', '/', '/privasi', '/minta-akses', '/lapor', '/TidakAda']);

it('memberi noindex pada pengalihan dan halaman galat, tanpa Set-Cookie', function () {
    foreach (['/Head001', '/TidakAda', '/Head001+'] as $path) {
        $r = $this->get($path);
        expect($r->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow', $path)
            ->and($r->headers->has('Set-Cookie'))->toBeFalse($path);
    }
});

it('mengirim no-store pada pengalihan dan galat', function () {
    expect($this->get('/Head001')->headers->get('Cache-Control'))->toContain('no-store')
        ->and($this->get('/TidakAda')->headers->get('Cache-Control'))->toContain('no-store');
});

it('mengirim HSTS hanya melalui HTTPS pada pengalihan dan halaman publik', function () {
    foreach (['/Head001', '/', '/lapor'] as $path) {
        expect($this->get('http://alias.test'.$path)->headers->has('Strict-Transport-Security'))->toBeFalse($path)
            ->and($this->get('https://alias.test'.$path)->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
    }
});

it('memasang X-Frame-Options pada halaman kata sandi tautan', function () {
    $this->t->forceFill(['kata_sandi_hash' => Hash::make('rahasia')])->saveQuietly();

    $r = $this->get('/Head001');

    expect($r->getStatusCode())->toBe(200)->and($r->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('CSP tidak melarang aset yang dipakai halaman publik (tanpa JS, gaya sebaris, gambar data)', function () {
    $csp = HeaderKeamananPublik::CSP;

    expect($csp)->toContain("style-src 'self' 'unsafe-inline'")->toContain('img-src')->toContain('data:');
    expect(file_get_contents(resource_path('views/layouts/publik.blade.php')))->not->toContain('<script');
});

it('X-Frame-Options tidak diterapkan secara global pada panel (Filament memakai cara sendiri)', function () {
    $r = $this->get('/panel/login');

    expect($r->headers->get('Content-Security-Policy'))->toBeNull();
});
