<?php

namespace App\Actions\Kunjungan;

use App\Enums\DimensiRekap;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Meringkas kunjungan satu hari ke tabel rekap (idempoten: dihitung ulang dari data mentah lalu diganti).
 * Pengunjung unik = jumlah ip_hash berbeda per hari (garam harian membuat hash tak dapat dikaitkan lintas hari).
 *
 * Konkurensi (STANDAR-TEKNIS §1b, tanpa kunci cache): seluruh perhitungan dan penggantian berada dalam SATU
 * transaksi. Jalankan bersamaan (mis. manual saat jadwal berjalan) saling menunggu lewat kunci baris InnoDB pada
 * DELETE/upsert, lalu mengulang hasil yang sama (idempoten). Kebuntuan (deadlock) diulang otomatis. Perintah
 * terjadwal juga dijaga withoutOverlapping() di routes/console.php.
 */
class RekapKunjungan
{
    public const MAKS_NILAI_PER_HARI = 20;

    private const PERCOBAAN_TRANSAKSI = 3;

    /** @return int jumlah tautan yang direkap */
    public function jalankan(CarbonInterface $tanggal): int
    {
        $hari = $tanggal->format('Y-m-d');
        $awal = $tanggal->copy()->startOfDay()->format('Y-m-d H:i:s');
        $akhir = $tanggal->copy()->endOfDay()->format('Y-m-d H:i:s');

        return DB::transaction(fn (): int => $this->rekap($hari, $awal, $akhir), self::PERCOBAAN_TRANSAKSI);
    }

    private function rekap(string $hari, string $awal, string $akhir): int
    {
        $sekarang = now()->format('Y-m-d H:i:s');

        // Penghapusan dulu: DELETE adalah pembacaan terkini yang menunggu transaksi lain pada hari yang sama selesai.
        DB::table('rekap_kunjungan_dimensi')->where('tanggal', $hari)->delete();

        $harian = DB::table('kunjungan_tautan')
            ->whereBetween('dikunjungi_pada', [$awal, $akhir])
            ->groupBy('tautan_pendek_id')
            ->selectRaw('tautan_pendek_id, sum(case when bot = 0 then 1 else 0 end) as klik, count(distinct case when bot = 0 then ip_hash end) as unik, sum(case when bot = 1 then 1 else 0 end) as bot')
            ->get();

        DB::table('rekap_kunjungan_harian')->where('tanggal', $hari)->whereNotIn('tautan_pendek_id', $harian->pluck('tautan_pendek_id'))->delete();

        foreach ($harian->chunk(500) as $kelompok) {
            DB::table('rekap_kunjungan_harian')->upsert(
                $kelompok->map(fn ($b) => [
                    'id' => (string) Str::uuid7(), 'tautan_pendek_id' => $b->tautan_pendek_id, 'tanggal' => $hari,
                    'jumlah_klik' => (int) $b->klik, 'jumlah_pengunjung_unik' => (int) $b->unik, 'jumlah_bot' => (int) $b->bot,
                    'created_at' => $sekarang, 'updated_at' => $sekarang,
                ])->all(),
                ['tautan_pendek_id', 'tanggal'],
                ['jumlah_klik', 'jumlah_pengunjung_unik', 'jumlah_bot', 'updated_at'],
            );
        }

        foreach (DimensiRekap::cases() as $dimensi) {
            $this->rekapDimensi($dimensi, $hari, $awal, $akhir);
        }

        return $harian->count();
    }

    private function rekapDimensi(DimensiRekap $dimensi, string $hari, string $awal, string $akhir): void
    {
        $kolom = $dimensi->value;

        $baris = DB::table('kunjungan_tautan')
            ->whereBetween('dikunjungi_pada', [$awal, $akhir])
            ->where('bot', false)
            ->groupBy('tautan_pendek_id', $kolom)
            ->selectRaw("tautan_pendek_id, {$kolom} as nilai, count(*) as jumlah")
            ->get()
            ->groupBy('tautan_pendek_id');

        $rows = [];
        foreach ($baris as $tautanId => $nilaiNilai) {
            $gabung = [];
            foreach ($nilaiNilai->sortByDesc('jumlah')->values() as $indeks => $b) {
                // Ekor panjang (> 20 nilai/hari) dilebur ke "(lainnya)".
                $nilai = $indeks >= self::MAKS_NILAI_PER_HARI ? '(lainnya)' : ($b->nilai === null || $b->nilai === '' ? $dimensi->nilaiKosong() : (string) $b->nilai);
                $gabung[$nilai] = ($gabung[$nilai] ?? 0) + (int) $b->jumlah;
            }

            foreach ($gabung as $nilai => $jumlah) {
                $rows[] = [
                    'id' => (string) Str::uuid7(), 'tautan_pendek_id' => $tautanId, 'tanggal' => $hari,
                    'dimensi' => $kolom, 'nilai' => mb_substr((string) $nilai, 0, 255), 'jumlah' => $jumlah,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $kelompok) {
            DB::table('rekap_kunjungan_dimensi')->insert($kelompok);
        }
    }
}
