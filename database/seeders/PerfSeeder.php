<?php

namespace Database\Seeders;

use App\Jobs\PindaiUlangAturan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * F10.2 — data sintetis untuk uji beban: 50.000 tautan dan 1.000.000 baris kunjungan.
 * HANYA lingkungan local; jalankan terhadap basis data khusus, mis.
 *   DB_DATABASE=database/perf.sqlite php artisan migrate --force
 *   DB_DATABASE=database/perf.sqlite php artisan db:seed --class=PerfSeeder
 */
class PerfSeeder extends Seeder
{
    public const JUMLAH_TAUTAN = 50_000;

    public const JUMLAH_KUNJUNGAN = 1_000_000;

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('PerfSeeder hanya untuk lingkungan local.');
        }

        PindaiUlangAturan::tanpaPindai(function (): void {
            $pemilik = $this->pemilik();
            $tautanId = $this->tautan($pemilik);
            $this->kunjungan($tautanId);
        });
    }

    private function pemilik(): string
    {
        $id = (string) Str::uuid7();
        DB::table('users')->insert([
            'id' => $id, 'name' => 'Pemilik Beban', 'email' => 'beban@unsil.ac.id', 'aktif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @return list<string> */
    private function tautan(string $pemilik): array
    {
        $ids = [];
        $sekarang = now()->format('Y-m-d H:i:s');

        foreach (array_chunk(range(1, self::JUMLAH_TAUTAN), 500) as $kelompok) {
            $baris = [];
            foreach ($kelompok as $i) {
                $id = (string) Str::uuid7();
                $url = "https://contoh{$i}.unsil.ac.id/halaman/{$i}";
                $ids[] = $id;
                $baris[] = [
                    'id' => $id, 'kode' => Str::random(7), 'kode_kustom' => false, 'judul' => "Tautan beban {$i}",
                    'url_tujuan' => $url, 'host_tujuan' => "contoh{$i}.unsil.ac.id", 'url_tujuan_hash' => hash('sha256', $url),
                    'jenis_kepemilikan' => 'pribadi', 'pemilik_id' => $pemilik, 'dibuat_oleh' => $pemilik, 'status' => 'aktif',
                    'pertama_aktif_pada' => $sekarang, 'jumlah_klik' => 0, 'created_at' => $sekarang, 'updated_at' => $sekarang,
                ];
            }
            DB::table('tautan_pendek')->insert($baris);
        }

        return $ids;
    }

    /** @param  list<string>  $tautanId */
    private function kunjungan(array $tautanId): void
    {
        $perangkat = ['desktop', 'ponsel', 'tablet', 'lainnya'];
        $peramban = ['Chrome', 'Firefox', 'Safari', 'Edge'];
        $total = count($tautanId);
        $dasar = now()->subDays(90)->getTimestamp();

        for ($mulai = 0; $mulai < self::JUMLAH_KUNJUNGAN; $mulai += 500) {
            $baris = [];
            for ($i = 0; $i < 500; $i++) {
                $n = $mulai + $i;
                $baris[] = [
                    'id' => (string) Str::uuid7(), 'tautan_pendek_id' => $tautanId[$n % $total],
                    'dikunjungi_pada' => date('Y-m-d H:i:s', $dasar + ($n * 7) % (90 * 86400)),
                    'ip_anonim' => '192.0.2.0', 'ip_hash' => hash('sha256', (string) ($n % 5000)),
                    'peramban' => $peramban[$n % 4], 'versi_peramban' => '124', 'os' => 'Windows',
                    'jenis_perangkat' => $perangkat[$n % 4], 'perujuk_host' => $n % 3 === 0 ? 'www.facebook.com' : null,
                    'bot' => $n % 20 === 0,
                ];
            }
            DB::table('kunjungan_tautan')->insert($baris);
        }
    }
}
