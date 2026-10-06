<?php

namespace App\Providers;

use App\Enums\Peran;
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
            RateLimiter::for($nama, fn (Request $request) => Limit::perMinutes($menit, $percobaan)->by($request->user()?->getKey() ?? $request->ip()));
        }
    }
}
