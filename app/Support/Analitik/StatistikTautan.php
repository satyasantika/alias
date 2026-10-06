<?php

namespace App\Support\Analitik;

use App\Enums\DimensiRekap;
use App\Models\TautanPendek;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistik satu tautan dari tabel rekap, ditambah data mentah untuk hari yang belum direkap (hari ini, dan
 * kemarin bila rekap belum berjalan). Bot tidak dihitung pada klik/unik. Unik dihitung per hari (garam harian).
 */
class StatistikTautan
{
    public function __construct(private readonly TautanPendek $tautan) {}

    /**
     * @return Collection<string, array{klik: int, unik: int, bot: int}> kunci tanggal Y-m-d, berurutan, tanpa lubang
     */
    public function klikHarian(int $hari): Collection
    {
        [$awal, $akhir] = $this->rentang($hari);

        $rekap = DB::table('rekap_kunjungan_harian')
            ->where('tautan_pendek_id', $this->tautan->getKey())
            ->whereBetween('tanggal', [$awal->format('Y-m-d'), $akhir->format('Y-m-d')])
            ->get()->keyBy(fn ($r) => CarbonImmutable::parse($r->tanggal)->format('Y-m-d'));

        $hasil = collect();
        for ($t = $awal; $t->lessThanOrEqualTo($akhir); $t = $t->addDay()) {
            $kunci = $t->format('Y-m-d');
            $baris = $rekap->get($kunci);

            if ($this->perluDataMentah($t, $baris !== null)) {
                $hasil[$kunci] = $this->hitungMentah($t);
            } else {
                $hasil[$kunci] = $baris === null
                    ? ['klik' => 0, 'unik' => 0, 'bot' => 0]
                    : ['klik' => (int) $baris->jumlah_klik, 'unik' => (int) $baris->jumlah_pengunjung_unik, 'bot' => (int) $baris->jumlah_bot];
            }
        }

        return $hasil;
    }

    /** @return array{klik: int, unik: int, bot: int} */
    public function total(int $hari): array
    {
        $harian = $this->klikHarian($hari);

        return ['klik' => $harian->sum('klik'), 'unik' => $harian->sum('unik'), 'bot' => $harian->sum('bot')];
    }

    /**
     * @return Collection<string, int> nilai => jumlah, urut menurun, maks $batas
     */
    public function dimensi(DimensiRekap $dimensi, int $hari, int $batas = 10): Collection
    {
        [$awal, $akhir] = $this->rentang($hari);
        $jumlah = [];

        $rekap = DB::table('rekap_kunjungan_dimensi')
            ->where('tautan_pendek_id', $this->tautan->getKey())
            ->where('dimensi', $dimensi->value)
            ->whereBetween('tanggal', [$awal->format('Y-m-d'), $akhir->format('Y-m-d')])
            ->get(['tanggal', 'nilai', 'jumlah']);

        $hariTerekap = DB::table('rekap_kunjungan_harian')
            ->where('tautan_pendek_id', $this->tautan->getKey())
            ->whereBetween('tanggal', [$awal->format('Y-m-d'), $akhir->format('Y-m-d')])
            ->pluck('tanggal')->map(fn ($t) => CarbonImmutable::parse($t)->format('Y-m-d'))->all();

        foreach ($rekap as $r) {
            $jumlah[$r->nilai] = ($jumlah[$r->nilai] ?? 0) + (int) $r->jumlah;
        }

        for ($t = $awal; $t->lessThanOrEqualTo($akhir); $t = $t->addDay()) {
            if (! $this->perluDataMentah($t, in_array($t->format('Y-m-d'), $hariTerekap, true))) {
                continue;
            }

            $kolom = $dimensi->value;
            $mentah = DB::table('kunjungan_tautan')
                ->where('tautan_pendek_id', $this->tautan->getKey())
                ->whereBetween('dikunjungi_pada', [$t->copy()->startOfDay()->format('Y-m-d H:i:s'), $t->copy()->endOfDay()->format('Y-m-d H:i:s')])
                ->where('bot', false)
                ->groupBy($kolom)
                ->selectRaw("{$kolom} as nilai, count(*) as jumlah")
                ->get();

            foreach ($mentah as $m) {
                $nilai = $m->nilai === null || $m->nilai === '' ? $dimensi->nilaiKosong() : (string) $m->nilai;
                $jumlah[$nilai] = ($jumlah[$nilai] ?? 0) + (int) $m->jumlah;
            }
        }

        arsort($jumlah);

        return collect(array_slice($jumlah, 0, $batas, true));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function rentang(int $hari): array
    {
        $akhir = CarbonImmutable::now()->startOfDay();

        return [$akhir->copy()->subDays(max(1, $hari) - 1), $akhir];
    }

    /** Hari ini selalu mentah; kemarin mentah bila rekap belum berjalan. */
    private function perluDataMentah(CarbonInterface $tanggal, bool $sudahDirekap): bool
    {
        if ($tanggal->isSameDay(CarbonImmutable::now())) {
            return true;
        }

        return ! $sudahDirekap && $tanggal->isSameDay(CarbonImmutable::now()->subDay());
    }

    /** @return array{klik: int, unik: int, bot: int} */
    private function hitungMentah(CarbonInterface $tanggal): array
    {
        $b = DB::table('kunjungan_tautan')
            ->where('tautan_pendek_id', $this->tautan->getKey())
            ->whereBetween('dikunjungi_pada', [$tanggal->copy()->startOfDay()->format('Y-m-d H:i:s'), $tanggal->copy()->endOfDay()->format('Y-m-d H:i:s')])
            ->selectRaw('sum(case when bot = 0 then 1 else 0 end) as klik, count(distinct case when bot = 0 then ip_hash end) as unik, sum(case when bot = 1 then 1 else 0 end) as bot')
            ->first();

        return ['klik' => (int) ($b->klik ?? 0), 'unik' => (int) ($b->unik ?? 0), 'bot' => (int) ($b->bot ?? 0)];
    }
}
