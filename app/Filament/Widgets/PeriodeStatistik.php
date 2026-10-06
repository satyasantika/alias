<?php

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;

/** Periode statistik: bulan terpilih (Y-m) atau 30 hari terakhir bila kosong. */
class PeriodeStatistik
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function dari(?string $bulan): array
    {
        if ($bulan !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            $awal = CarbonImmutable::createFromFormat('Y-m-d', $bulan.'-01')->startOfDay();

            return [$awal, $awal->endOfMonth()->startOfDay()];
        }

        $sampai = CarbonImmutable::now()->startOfDay();

        return [$sampai->subDays(29), $sampai];
    }
}
