<?php

namespace Database\Seeders;

use App\Enums\JenisAturanDomain;
use App\Models\AturanDomain;
use Illuminate\Database\Seeder;

/** 03-SKEMA §6.4. `forms.gle` sengaja TIDAK diblokir (domain pendek sah milik Google Forms). */
class AturanDomainSeeder extends Seeder
{
    private const BLOKIR = [
        'bit.ly', 's.id', 'tinyurl.com', 'cutt.ly', 'shorturl.at', 'rb.gy', 'is.gd', 'ow.ly', 't.ly', 'tiny.cc',
        'rebrand.ly', 'bl.ink', 'shorturl.asia', 'lnkd.in', 't.co', 'goo.su', 'v.gd',
    ];

    private const IZINKAN = [
        '*.unsil.ac.id', 'docs.google.com', 'drive.google.com', 'forms.gle', 'meet.google.com', 'zoom.us', '*.zoom.us',
        'youtube.com', 'youtu.be', '*.go.id', '*.kemdiktisaintek.go.id',
    ];

    public function run(): void
    {
        foreach (self::BLOKIR as $pola) {
            $this->buat($pola, JenisAturanDomain::Blokir, 'pemendek pihak ketiga');
        }

        foreach (array_filter([config('alias.domain_pendek'), config('alias.domain_panel')]) as $pola) {
            $this->buat((string) $pola, JenisAturanDomain::Blokir, 'domain Alias sendiri');
        }

        foreach (self::IZINKAN as $pola) {
            $this->buat($pola, JenisAturanDomain::Izinkan, 'daftar putih (mode daftar_putih)');
        }
    }

    private function buat(string $pola, JenisAturanDomain $jenis, string $alasan): void
    {
        AturanDomain::firstOrCreate(['pola_host' => mb_strtolower($pola)], ['jenis' => $jenis->value, 'alasan' => $alasan, 'aktif' => true]);
    }
}
