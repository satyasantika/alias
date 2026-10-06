<?php

use Symfony\Component\Yaml\Yaml;

it('mendefinisikan layanan produksi tanpa MySQL, dengan healthcheck dan restart', function () {
    $compose = Yaml::parseFile(base_path('compose.produksi.yaml'));
    $layanan = $compose['services'];

    expect(array_keys($layanan))->toEqualCanonicalizing(['alias-php', 'alias-nginx', 'alias-horizon', 'alias-scheduler', 'alias-redis'])
        ->and(json_encode($compose))->not->toContain('mysql:');

    foreach ($layanan as $nama => $konfigurasi) {
        if ($nama !== 'alias-scheduler') {
            expect(array_key_exists('healthcheck', $konfigurasi))->toBeTrue("{$nama} tanpa healthcheck");
        }
        expect(($konfigurasi['restart'] ?? $compose['x-app']['restart'] ?? null))->toBe('unless-stopped', $nama);
    }
});

it('menjaga nginx produksi: tanpa cache proxy, real_ip, batas unggahan 2M, aset ber-cache saja', function () {
    $konf = file_get_contents(base_path('docker/nginx/produksi.conf.template'));

    expect($konf)->toContain('set_real_ip_from ${NGINX_REAL_IP_FROM}')->toContain('client_max_body_size 2M')
        ->toContain('proxy_cache off')->toContain('gzip on')->toContain('server_tokens off')
        ->and($konf)->not->toContain('proxy_cache_path')->not->toContain('fastcgi_cache_path');
});

it('tidak memuat rahasia pada contoh env produksi dan menutup mode debug', function () {
    $env = collect(file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        ->reject(fn ($b) => str_starts_with($b, '#'))
        ->mapWithKeys(function ($baris) {
            [$k, $v] = array_pad(explode('=', $baris, 2), 2, '');

            return [$k => $v];
        });

    expect($env['APP_DEBUG'])->toBe('false')->and($env['APP_ENV'])->toBe('production')->and($env['APP_KEY'])->toBe('')
        ->and($env['MAIL_PASSWORD'])->toBe('')->and($env['GOOGLE_CLIENT_SECRET'])->toBe('')->and($env['SEED_PASSWORD'])->toBe('')
        ->and($env['SESSION_SECURE_COOKIE'])->toBe('true')->and($env['CACHE_STORE'])->toBe('redis')->and($env['ALIAS_CACHE_LOOKUP'])->toBe('false');
});

it('menyiapkan Dockerfile bertahap tanpa dev dependency dan OPcache produksi', function () {
    $docker = file_get_contents(base_path('docker/php/Dockerfile'));

    expect($docker)->toContain('composer install --no-dev')->toContain('npm run build')->toContain('USER www-data')
        ->and(file_get_contents(base_path('docker/php/opcache.ini')))->toContain('opcache.validate_timestamps=0')
        ->and(file_get_contents(base_path('docker/php/fpm-pool.conf')))->toContain('pm.max_children = 20')
        ->and(file_get_contents(base_path('.dockerignore')))->toContain('.env')->toContain('bootstrap/cache/*.php');
});

it('tidak melacak berkas rahasia di git', function () {
    $terlacak = shell_exec('git ls-files 2>/dev/null') ?? '';

    foreach (['.env', '.env.production', 'auth.json'] as $berkas) {
        expect(preg_match('/^'.preg_quote($berkas, '/').'$/m', $terlacak))->toBe(0, $berkas);
    }
    expect($terlacak)->not->toMatch('/\.sqlite/')->not->toMatch('/\.sql(\.gz)?$/m');
});
