<?php

$halaman = ['index', 'pengguna', 'pengelola', 'admin', 'pemantau', 'super-admin'];
$terdaftar = ['pengguna', 'pengelola', 'admin', 'pemantau'];

it('menyediakan panduan HTML mandiri per peran dengan tangkapan layar tertanam', function (string $nama) {
    $berkas = public_path("panduan/{$nama}.html");
    expect(file_exists($berkas))->toBeTrue();
    $html = file_get_contents($berkas);

    expect($html)->toStartWith('<!DOCTYPE html>')->toContain('lang="id"')->toContain('<title>')
        ->and($html)->not->toContain('<script')                      // HTML saja: tanpa JavaScript
        ->and(preg_match_all('#(src|href)="https?://#', $html))->toBe(0); // tanpa sumber daya eksternal

    if ($nama !== 'index') {
        expect(substr_count($html, 'src="data:image/jpeg;base64,'))->toBeGreaterThanOrEqual(3)
            ->and($html)->toContain('alt="Tangkapan layar:')->toContain('Yang dapat Anda lakukan');
    }
})->with($halaman);

it('menautkan seluruh panduan dari landing page dan memakai tautan relatif yang aman di sub-path', function () {
    $r = $this->get('/')->assertOk();
    foreach (['pengguna', 'pengelola', 'admin', 'pemantau'] as $p) {
        $r->assertSee("/panduan/{$p}.html", false);
    }
    $r->assertDontSee('super-admin', false)->assertDontSee('Super admin');

    $index = file_get_contents(public_path('panduan/index.html'));
    expect($index)->toContain('href="../panel/login"')->toContain('href="pengguna.html"')->toContain('href="../minta-akses"');
});

it('mencakup peran sesuai matriks dan menyebut batas hak yang benar', function () {
    expect(file_get_contents(public_path('panduan/pemantau.html')))->toContain('hanya melihat statistik agregat')
        ->and(file_get_contents(public_path('panduan/super-admin.html')))->toContain('Pengaturan sistem')->toContain('Log login')
        ->and(file_get_contents(public_path('panduan/admin.html')))->toContain('Antrean persetujuan slug')->toContain('Impor massal CSV')
        ->and(file_get_contents(public_path('panduan/pengelola.html')))->toContain('Kelola anggota')
        ->and(file_get_contents(public_path('panduan/pengguna.html')))->toContain('Buat tautan');
});

it('dapat dibangun ulang dari sumber isi (tools/panduan)', function () {
    expect(file_exists(base_path('tools/panduan/isi.php')))->toBeTrue()
        ->and(array_keys(require base_path('tools/panduan/isi.php')))->toBe(['pengguna', 'pengelola', 'admin', 'pemantau', 'super-admin']);
});

it('menyembunyikan panduan super-admin dari landing page, indeks, dan navigasi semua panduan', function () {
    foreach (['index', 'pengguna', 'pengelola', 'admin', 'pemantau'] as $nama) {
        $html = file_get_contents(public_path("panduan/{$nama}.html"));
        expect($html)->not->toContain('super-admin.html')->not->toContain('Super admin', $nama);
    }

    $landing = $this->get('/')->getContent();
    expect($landing)->not->toContain('super-admin')->not->toContain('Super admin');
    expect(file_get_contents(public_path('panduan/super-admin.html')))->toContain('noindex');
});
