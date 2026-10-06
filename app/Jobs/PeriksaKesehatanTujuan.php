<?php

namespace App\Jobs;

use App\Enums\StatusCekTujuan;
use App\Events\TujuanBermasalah;
use App\Exceptions\HostSibuk;
use App\Models\TautanPendek;
use App\Support\Tujuan\PemeriksaTujuan;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * BR-29. Status ini informatif dan tidak memengaruhi pengalihan. Dua kegagalan beruntun → "bermasalah".
 */
class PeriksaKesehatanTujuan implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const GAGAL_BERUNTUN_MAKS = 2;

    public int $tries = 2;

    public int $timeout = 30;

    public int $uniqueFor = 120;

    public function __construct(public readonly string $tautanId)
    {
        $this->onQueue('cek-tujuan');
    }

    public static function untuk(TautanPendek $tautan): void
    {
        self::dispatch($tautan->getKey())->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->tautanId;
    }

    public function handle(PemeriksaTujuan $pemeriksa): void
    {
        $tautan = TautanPendek::query()->find($this->tautanId);

        if ($tautan === null) {
            return;
        }

        try {
            $hasil = $pemeriksa->periksa($tautan->url_tujuan);
        } catch (HostSibuk) {
            $this->release(5);

            return;
        }

        $gagal = $hasil->berhasil() ? 0 : min(255, $tautan->gagal_cek_beruntun + 1);
        $statusSebelumnya = $tautan->status_cek_tujuan;

        $status = match (true) {
            $hasil->berhasil() => $hasil->status,
            $gagal >= self::GAGAL_BERUNTUN_MAKS => StatusCekTujuan::Bermasalah,
            default => $statusSebelumnya, // kegagalan pertama: belum cukup bukti
        };

        // Query builder: penanda cek tidak boleh memicu jejak audit atau updated_at.
        TautanPendek::query()->whereKey($tautan->getKey())->toBase()->update([
            'status_cek_tujuan' => $status->value,
            'kode_http_terakhir' => $hasil->kodeHttp,
            'dicek_tujuan_pada' => now()->format('Y-m-d H:i:s'),
            'gagal_cek_beruntun' => $gagal,
        ]);

        if ($status === StatusCekTujuan::Bermasalah && $statusSebelumnya !== StatusCekTujuan::Bermasalah) {
            TujuanBermasalah::dispatch($tautan->refresh());
        }
    }
}
