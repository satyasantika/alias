<?php

namespace App\Http\Controllers\Auth;

use App\Enums\MetodeLogin;
use App\Enums\PeristiwaLogin;
use App\Http\Controllers\Controller;
use App\Models\LogLogin;
use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Login Google OPSIONAL (ALIAS_LOGIN_GOOGLE). Tidak pernah membuat akun (BR-31) dan tidak berlaku
 * bagi peran yang wajib MFA (agar tantangan TOTP tidak terlewati).
 */
class GoogleAuthController extends Controller
{
    public function arahkan(): Response
    {
        $this->pastikanAktif();

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->with(['hd' => config('alias.domain_surel')[0] ?? 'unsil.ac.id'])->redirect();
    }

    public function kembali(Request $request): RedirectResponse
    {
        $this->pastikanAktif();

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->tolak('Masuk dengan Google gagal. Coba lagi.');
        }

        $surel = Str::lower((string) $google->getEmail());
        $terverifikasi = (bool) ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false);

        if (! $terverifikasi || ! SurelDomainUnsil::lolos($surel)) {
            $this->catat(PeristiwaLogin::DitolakDomain, $surel, null, $request);

            return $this->tolak('Hanya akun Google dengan surel @unsil.ac.id yang terverifikasi yang dapat masuk.');
        }

        $user = User::query()->where('email', $surel)->first();

        if ($user === null) {
            $this->catat(PeristiwaLogin::Gagal, $surel, null, $request);

            return $this->tolak('Akun Anda belum terdaftar di Alias. Silakan minta akses melalui '.url('/minta-akses').'.');
        }

        if (! $user->aktif || $user->terkunci()) {
            $this->catat(PeristiwaLogin::DitolakNonaktif, $surel, $user, $request);

            return $this->tolak('Akun tidak aktif atau sedang dikunci.');
        }

        if ($user->wajibMfa()) {
            $this->catat(PeristiwaLogin::Gagal, $surel, $user, $request);

            return $this->tolak('Peran admin wajib masuk dengan kata sandi dan MFA.');
        }

        if ($user->google_id !== null && $user->google_id !== (string) $google->getId()) {
            $this->catat(PeristiwaLogin::Gagal, $surel, $user, $request);

            return $this->tolak('Akun Google tidak cocok dengan akun Alias ini.');
        }

        $user->forceFill(['google_id' => (string) $google->getId()])->saveQuietly();

        $request->attributes->set('metode_login', MetodeLogin::Google);
        Auth::guard()->login($user);
        $request->session()->regenerate();

        return redirect()->intended(Filament::getPanel('alias')->getUrl());
    }

    private function pastikanAktif(): void
    {
        abort_unless(config('alias.login_google'), 404);
    }

    private function tolak(string $pesan): RedirectResponse
    {
        return redirect()->to(Filament::getPanel('alias')->getLoginUrl())->with('status', $pesan);
    }

    private function catat(PeristiwaLogin $peristiwa, string $surel, ?User $user, Request $request): void
    {
        LogLogin::create([
            'user_id' => $user?->getKey(),
            'email' => $surel,
            'peristiwa' => $peristiwa,
            'metode' => MetodeLogin::Google,
            'ip' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
