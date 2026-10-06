<?php

namespace App\Support\Analitik;

use App\Enums\Peran;
use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistik agregat menurut lingkup peran: admin & pemantau → seluruh fakultas; pengelola → unit yang ia kelola;
 * selain itu → tautan yang terlihat olehnya. Sumber: tabel rekap (≤ kemarin) + kunjungan mentah hari ini; bot tidak dihitung.
 */
class StatistikAgregat
{
    public function __construct(private readonly User $pengguna) {}

    public function lingkup(): string
    {
        if ($this->pengguna->hasAnyRole([Peran::SuperAdmin->value, Peran::AdminAlias->value, Peran::Pemantau->value])) {
            return 'semua';
        }

        return $this->pengguna->unitDikelola()->exists() ? 'unit' : 'milik';
    }

    /** @return list<string>|null id unit yang tercakup; null = semua unit */
    public function unitTercakup(): ?array
    {
        return match ($this->lingkup()) {
            'semua' => null,
            'unit' => $this->pengguna->unitDikelola()->pluck('unit.id')->all(),
            default => [],
        };
    }

    /**
     * Klik per unit (tautan milik unit) pada periode.
     *
     * @return Collection<string, int> nama unit => klik, urut menurun
     */
    public function klikPerUnit(CarbonImmutable $dari, CarbonImmutable $sampai): Collection
    {
        $baris = $this->klikPerKolom($dari, $sampai, 't.unit_id', unitSaja: true);

        $nama = DB::table('unit')->whereIn('id', $baris->keys())->pluck('nama', 'id');

        return $baris->mapWithKeys(fn (int $klik, int|string $unitId) => [(string) ($nama[$unitId] ?? $unitId) => $klik])->sortDesc();
    }

    /**
     * Rekap bulanan per unit: tautan aktif, tautan baru pada periode, total klik.
     *
     * @return Collection<int, array{unit: string, aktif: int, baru: int, klik: int}>
     */
    public function rekapUnit(CarbonImmutable $dari, CarbonImmutable $sampai): Collection
    {
        $klik = $this->klikPerKolom($dari, $sampai, 't.unit_id', unitSaja: true);
        $tercakup = $this->unitTercakup();

        $unit = DB::table('unit')->whereNull('deleted_at')
            ->when($tercakup !== null, fn ($q) => $q->whereIn('id', $tercakup))
            ->orderBy('nama')->get(['id', 'nama']);

        $aktif = DB::table('tautan_pendek')->whereNull('deleted_at')->where('jenis_kepemilikan', 'unit')->where('status', StatusTautan::Aktif->value)
            ->groupBy('unit_id')->selectRaw('unit_id, count(*) as n')->pluck('n', 'unit_id');
        $baru = DB::table('tautan_pendek')->whereNull('deleted_at')->where('jenis_kepemilikan', 'unit')
            ->whereBetween('created_at', [$dari->startOfDay()->format('Y-m-d H:i:s'), $sampai->endOfDay()->format('Y-m-d H:i:s')])
            ->groupBy('unit_id')->selectRaw('unit_id, count(*) as n')->pluck('n', 'unit_id');

        return $unit->map(fn ($u) => [
            'unit' => (string) $u->nama,
            'aktif' => (int) ($aktif[$u->id] ?? 0),
            'baru' => (int) ($baru[$u->id] ?? 0),
            'klik' => (int) ($klik[$u->id] ?? 0),
        ])->values();
    }

    /**
     * Tautan teratas pada periode. Hanya kode, judul, unit, dan klik (aman untuk pemantau).
     *
     * @return Collection<int, array{kode: string, judul: string, unit: string, klik: int}>
     */
    public function tautanTeratas(CarbonImmutable $dari, CarbonImmutable $sampai, int $batas = 10): Collection
    {
        $klik = $this->klikPerKolom($dari, $sampai, 't.id', unitSaja: false)->sortDesc()->take($batas);

        $tautan = DB::table('tautan_pendek as t')->leftJoin('unit as u', 'u.id', '=', 't.unit_id')
            ->whereIn('t.id', $klik->keys())->get(['t.id', 't.kode', 't.judul', 'u.nama as unit'])->keyBy('id');

        return $klik->map(fn (int $jumlah, int|string $id) => [
            'kode' => (string) $tautan[$id]->kode,
            'judul' => (string) $tautan[$id]->judul,
            'unit' => (string) ($tautan[$id]->unit ?? '—'),
            'klik' => $jumlah,
        ])->values();
    }

    /**
     * Total klik manusia pada periode untuk tautan pribadi milik pengguna (ringkasan "tautan saya").
     */
    public function klikTautanSaya(int $hari = 30): int
    {
        $sampai = CarbonImmutable::now()->startOfDay();
        $dari = $sampai->subDays($hari - 1);

        return (int) $this->klikPerKolom($dari, $sampai, 't.pemilik_id', unitSaja: false, hanyaSaya: true)->get($this->pengguna->getKey(), 0);
    }

    /**
     * @return Collection<int|string, int> kolom pengelompok => klik
     */
    private function klikPerKolom(CarbonImmutable $dari, CarbonImmutable $sampai, string $kolom, bool $unitSaja, bool $hanyaSaya = false): Collection
    {
        $hasil = [];
        $hariIni = CarbonImmutable::now()->startOfDay();

        $rekap = $this->terapkanLingkup(
            DB::table('rekap_kunjungan_harian as r')->join('tautan_pendek as t', 't.id', '=', 'r.tautan_pendek_id')
                ->whereBetween('r.tanggal', [$dari->format('Y-m-d'), min($sampai, $hariIni->subDay())->format('Y-m-d')]),
            $unitSaja, $hanyaSaya,
        )->groupBy($kolom)->selectRaw("{$kolom} as kunci, sum(r.jumlah_klik) as klik")->get();

        foreach ($rekap as $b) {
            if ($b->kunci !== null) {
                $hasil[$b->kunci] = ($hasil[$b->kunci] ?? 0) + (int) $b->klik;
            }
        }

        if ($sampai->greaterThanOrEqualTo($hariIni)) {
            $mentah = $this->terapkanLingkup(
                DB::table('kunjungan_tautan as k')->join('tautan_pendek as t', 't.id', '=', 'k.tautan_pendek_id')
                    ->where('k.bot', false)->where('k.dikunjungi_pada', '>=', $hariIni->format('Y-m-d H:i:s')),
                $unitSaja, $hanyaSaya,
            )->groupBy($kolom)->selectRaw("{$kolom} as kunci, count(*) as klik")->get();

            foreach ($mentah as $b) {
                if ($b->kunci !== null) {
                    $hasil[$b->kunci] = ($hasil[$b->kunci] ?? 0) + (int) $b->klik;
                }
            }
        }

        /** @var Collection<int|string, int> $disaring */
        $disaring = collect($hasil)->filter(fn (int $n) => $n > 0);

        return $disaring;
    }

    private function terapkanLingkup(Builder $kueri, bool $unitSaja, bool $hanyaSaya): Builder
    {
        $kueri->whereNull('t.deleted_at');

        if ($unitSaja) {
            $kueri->where('t.jenis_kepemilikan', 'unit');
        }

        if ($hanyaSaya) {
            return $kueri->where('t.jenis_kepemilikan', 'pribadi')->where('t.pemilik_id', $this->pengguna->getKey());
        }

        return match ($this->lingkup()) {
            'semua' => $kueri,
            'unit' => $kueri->whereIn('t.unit_id', $this->unitTercakup() ?? []),
            default => $kueri->whereIn('t.id', TautanPendek::query()->terlihatOleh($this->pengguna)->select('id')->toBase()),
        };
    }
}
