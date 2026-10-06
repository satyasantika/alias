<?php

namespace Database\Seeders;

use App\Enums\CaraCocok;
use App\Enums\JenisSlugTerlarang;
use App\Models\SlugTerlarang;
use Illuminate\Database\Seeder;

/** 03-SKEMA §6.3. Idempoten. */
class SlugTerlarangSeeder extends Seeder
{
    private const SISTEM_TAMBAHAN = [
        'admin', 'administrator', 'api', 'app', 'assets', 'auth', 'build', 'cdn', 'css', 'dashboard', 'dasbor', 'docs',
        'filament', 'help', 'bantuan', 'horizon', 'img', 'images', 'js', 'lapor', 'livewire', 'login', 'logout', 'masuk',
        'keluar', 'minta-akses', 'panel', 'password', 'pratinjau', 'preview', 'privasi', 'profile', 'profil', 'qr',
        'register', 'daftar', 'reset-password', 'root', 'sanctum', 'settings', 'pengaturan', 'static', 'status',
        'storage', 'support', 'telescope', 'pulse', 'test', 'up', 'health', 'vendor', 'verify', 'www', 'mail',
        'webhook', 'oauth', 'google', 'sso', 'boost', 'mcp', 'tentang', 'kontak', 'syarat', 'kebijakan',
    ];

    private const KELEMBAGAAN = [
        'unsil', 'fkip', 'rektor', 'rektorat', 'dekan', 'dekanat', 'wakil-dekan', 'pmb', 'spmb', 'siakad', 'ppg',
        'lppm', 'lpm', 'bem', 'dpm', 'humas', 'resmi', 'official', 'akreditasi', 'pengumuman',
    ];

    public function run(): void
    {
        foreach (array_unique([...config('alias.segmen_sistem'), ...self::SISTEM_TAMBAHAN]) as $pola) {
            $this->buat($pola, JenisSlugTerlarang::CadanganSistem, CaraCocok::Persis);
        }

        foreach (self::KELEMBAGAAN as $pola) {
            $this->buat($pola, JenisSlugTerlarang::CadanganKelembagaan, CaraCocok::Persis);
        }

        foreach (file(database_path('seeders/data/kata-tidak-pantas.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
            if (str_starts_with($baris, '#')) {
                continue;
            }
            $this->buat(trim($baris), JenisSlugTerlarang::TidakPantas, CaraCocok::Mengandung);
        }
    }

    private function buat(string $pola, JenisSlugTerlarang $jenis, CaraCocok $cara): void
    {
        SlugTerlarang::firstOrCreate(
            ['pola' => mb_strtolower($pola), 'cara_cocok' => $cara->value],
            ['jenis' => $jenis->value, 'aktif' => true],
        );
    }
}
