<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Penjadwal (02-ARSITEKTUR §8) — zona waktu Asia/Jakarta, tanpa tumpang tindih
|--------------------------------------------------------------------------
*/
$jadwal = fn ($acara) => $acara->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();

$jadwal(Schedule::command('alias:rekap-kunjungan')->dailyAt('00:20'));
$jadwal(Schedule::command('alias:pangkas-kunjungan')->dailyAt('01:10'));
$jadwal(Schedule::command('alias:pangkas-log-login')->dailyAt('01:30'));
$jadwal(Schedule::command('alias:tolak-kedaluwarsa')->dailyAt('06:00'));
$jadwal(Schedule::command('alias:ingatkan-kedaluwarsa')->dailyAt('07:05'));
$jadwal(Schedule::command('alias:periksa-tujuan')->weeklyOn(0, '02:15'));
$jadwal(Schedule::command('alias:laporan-yatim')->weeklyOn(1, '07:10'));
$jadwal(Schedule::command('alias:bersihkan-tmp')->hourly());
$jadwal(Schedule::command('activitylog:clean')->monthly());

/*
|--------------------------------------------------------------------------
| Profil Ringan (STANDAR-TEKNIS §1b): tanpa Horizon/Redis
|--------------------------------------------------------------------------
| Penjadwal menyalakan worker tiap menit dan berhenti saat antrean kosong; jeda proses job paling lama ±1 menit.
| retry_after (config/queue.php) harus lebih besar dari --timeout: 90 > 60 dan 960 > 900.
*/
Schedule::command('queue:work database --queue=default --stop-when-empty --max-time=55 --timeout=60 --tries=3')
    ->everyMinute()->withoutOverlapping(2)->runInBackground();

Schedule::command('queue:work database-berat --queue=berat --stop-when-empty --max-time=600 --timeout=900 --tries=2 --memory=512')
    ->everyMinute()->withoutOverlapping(30)->runInBackground();

// Detak penjadwal: dibaca /api/health (peringatan bila > 5 menit). Cap waktu (int), bukan objek Carbon:
// cache `database` tidak mengembalikan objek (cache.serializable_classes = false).
Schedule::call(fn () => cache()->put(config('app.kode').':detak-penjadwal', now()->getTimestamp(), 600))
    ->everyMinute()->name('detak-penjadwal');

Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:00');
Schedule::command('queue:prune-batches --hours=168')->dailyAt('03:05');
