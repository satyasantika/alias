<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Penjadwal (02-ARSITEKTUR §8) — zona waktu Asia/Jakarta, tanpa tumpang tindih
|--------------------------------------------------------------------------
*/
$jadwal = fn ($acara) => $acara->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();

$jadwal(Schedule::command('horizon:snapshot')->everyFiveMinutes());
$jadwal(Schedule::command('alias:rekap-kunjungan')->dailyAt('00:20'));
$jadwal(Schedule::command('alias:pangkas-kunjungan')->dailyAt('01:10'));
$jadwal(Schedule::command('alias:pangkas-log-login')->dailyAt('01:30'));
$jadwal(Schedule::command('alias:tolak-kedaluwarsa')->dailyAt('06:00'));
$jadwal(Schedule::command('alias:ingatkan-kedaluwarsa')->dailyAt('07:05'));
$jadwal(Schedule::command('alias:periksa-tujuan')->weeklyOn(0, '02:15'));
$jadwal(Schedule::command('alias:laporan-yatim')->weeklyOn(1, '07:10'));
$jadwal(Schedule::command('alias:bersihkan-tmp')->hourly());
$jadwal(Schedule::command('activitylog:clean')->monthly());
