<?php

return [
    // Domain pendek & panel; kosong = satu domain (lokal). Lihat docs 02-ARSITEKTUR §5.1.
    'domain_pendek' => env('ALIAS_DOMAIN_PENDEK'),
    'domain_panel' => env('ALIAS_DOMAIN_PANEL'),

    // BR-32: domain surel yang diizinkan (persis, case-insensitive).
    // Sakelar sementara MFA aplikasi autentikator (BR-34). false = tidak ada pengaturan/tantangan MFA dan
    // tidak ada pemaksaan MFA admin. Admin tetap tidak boleh masuk lewat Google.
    'mfa_aktif' => (bool) env('ALIAS_MFA_AKTIF', true),

    'domain_surel' => array_values(array_filter(array_map('trim', explode(',', (string) env('ALIAS_DOMAIN_SUREL', 'unsil.ac.id,staff.unsil.ac.id'))))),

    // BR-03(b), BR-36: segmen pertama rute sistem yang tidak boleh menjadi kode/slug.
    'segmen_sistem' => [
        'panel', 'panduan', 'admin', 'horizon', 'livewire', 'filament', 'storage', 'build', 'vendor', 'up', 'api', 'lapor',
        'minta-akses', 'privasi', 'auth', 'login', 'logout', 'register', 'password', 'reset-password',
        'forgot-password', 'email', 'sanctum', 'boost', 'mcp', 'health', 'favicon.ico', 'robots.txt',
    ],

    // Proksi tepercaya agar IP klien benar di belakang reverse proxy (env TRUSTED_PROXIES).
    'proksi_tepercaya' => env('TRUSTED_PROXIES'),

    // BR-01
    'panjang_kode' => 7,

    // BR-24: [percobaan, menit]
    'batas_laju' => [
        // Dapat disesuaikan (mis. NAT kampus): ALIAS_LAJU_PENGALIHAN=<permintaan per menit per IP>.
        'pengalihan' => [(int) env('ALIAS_LAJU_PENGALIHAN', 300), 1],
        'pratinjau' => [60, 1],
        'buat-tautan' => [30, 60],
        'lapor' => [5, 60],
        'minta-akses' => [3, 60],
        'qr' => [60, 1],
        'kata-sandi-tautan' => [5, 1],
    ],

    // BR-08
    'kata_kunci_tujuan_terlarang' => ['gacor', 'togel', 'maxwin', 'slot88', 'judol', 'sbobet', 'casino', 'kasino'],

    // BR-06: host internal yang boleh me-resolve ke IP privat.
    'domain_internal_diizinkan' => array_values(array_filter(array_map('trim', explode(',', (string) env('ALIAS_DOMAIN_INTERNAL_DIIZINKAN', '*.unsil.ac.id'))))),

    // BR-22
    'namespace_unit' => (bool) env('ALIAS_NAMESPACE_UNIT', false),

    // 02 §4: tidak diaktifkan tanpa data uji beban F10.2.
    'cache_lookup' => (bool) env('ALIAS_CACHE_LOOKUP', false),

    // Hanya untuk seeder lokal/staging (PenggunaAwalSeeder).
    'seed_password' => env('SEED_PASSWORD'),

    // Hanya untuk uji: izinkan URL::forceRootUrl pada lingkungan testing.
    'paksa_sub_path' => false,

    'login_google' => (bool) env('ALIAS_LOGIN_GOOGLE', false),
];
