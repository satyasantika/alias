<?php

namespace App\Jobs;

use App\Models\TautanPendek;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** BR-29. Pemeriksaan sebenarnya diisi pada F6.3; kini hanya kerangka agar Action dapat mengantre. */
class PeriksaKesehatanTujuan implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(public readonly string $tautanId)
    {
        $this->onQueue('cek-tujuan');
    }

    public static function untuk(TautanPendek $tautan): void
    {
        self::dispatch($tautan->getKey())->afterCommit();
    }

    public function handle(): void
    {
        // F6.3
    }
}
