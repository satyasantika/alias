<?php

namespace App\Providers;

use App\Enums\Peran;
use App\Models\Export;
use App\Models\FailedImportRow;
use App\Models\Import;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tabel impor Filament memakai UUID (STANDAR-TEKNIS §4a).
        $this->app->bind(\Filament\Actions\Imports\Models\Import::class, Import::class);
        $this->app->bind(\Filament\Actions\Imports\Models\FailedImportRow::class, FailedImportRow::class);
        $this->app->bind(\Filament\Actions\Exports\Models\Export::class, Export::class);

        // TRUSTED_PROXIES: daftar IP/CIDR dipisah koma, atau "*" (perlu verifikasi topologi UPT TIK).
        $proksi = config('alias.proksi_tepercaya');
        if (filled($proksi)) {
            TrustProxies::at($proksi === '*' ? '*' : array_map('trim', explode(',', (string) $proksi)));
        }
    }

    public function boot(): void
    {
        Gate::before(fn (?User $user) => $user?->hasRole(Peran::SuperAdmin->value) ? true : null);

        $this->aturBatasLaju();

        Carbon::setLocale('id');
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    /** BR-24: batas laju dari config/alias.php ([percobaan, menit]). */
    private function aturBatasLaju(): void
    {
        foreach (config('alias.batas_laju') as $nama => [$percobaan, $menit]) {
            RateLimiter::for($nama, function (Request $request) use ($nama, $menit, $percobaan) {
                $kunci = $request->user()?->getKey() ?? $request->ip();

                // BR-35: kata sandi tautan dibatasi per kombinasi IP dan kode.
                if ($nama === 'kata-sandi-tautan') {
                    $kunci .= '|'.$request->route('kode');
                }

                return Limit::perMinutes($menit, $percobaan)->by($kunci);
            });
        }
    }
}
