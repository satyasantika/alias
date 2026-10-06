<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $db = $this->periksa(fn () => DB::select('select 1'));
        $redis = $this->periksa(fn () => Redis::connection()->ping());
        $antrean = $redis === 'ok'
            ? $this->periksa(fn () => Queue::connection('redis')->size('kunjungan'), true)
            : null;

        $sehat = $db === 'ok' && $redis === 'ok';

        return response()->json([
            'app' => config('app.name'),
            'versi' => config('app.versi'),
            'db' => $db,
            'redis' => $redis,
            'antrean' => $antrean,
        ], $sehat ? 200 : 503);
    }

    private function periksa(callable $cek, bool $kembalikanNilai = false): string|int
    {
        try {
            $hasil = $cek();

            return $kembalikanNilai ? (int) $hasil : 'ok';
        } catch (Throwable) {
            return $kembalikanNilai ? -1 : 'gagal';
        }
    }
}
