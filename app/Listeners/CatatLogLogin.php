<?php

namespace App\Listeners;

use App\Enums\MetodeLogin;
use App\Enums\PeristiwaLogin;
use App\Models\LogLogin;
use App\Models\User;
use App\Notifications\AkunTerkunci;
use App\Rules\SurelDomainUnsil;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** BR-34: catat setiap peristiwa autentikasi (IP utuh, khusus keamanan) dan kunci akun setelah 10 gagal/30 menit. */
class CatatLogLogin
{
    public const BATAS_GAGAL = 10;

    public const MENIT_JENDELA = 30;

    public const MENIT_KUNCI = 15;

    public function handleLogin(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        Cache::forget($this->kunciHitung($user->email));
        $user->forceFill([
            'terakhir_masuk_pada' => now(),
            'metode_login_terakhir' => $this->metode()->value,
        ])->saveQuietly();

        $this->catat(PeristiwaLogin::Berhasil, $user->email, $user);
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $surel = Str::lower((string) ($event->credentials['email'] ?? $user->email ?? ''));

        if ($user !== null && $this->kataSandiBenar($user, $event->credentials)) {
            // Kredensial benar tetapi akun tidak boleh masuk: bukan tebakan kata sandi, tidak dihitung.
            $peristiwa = SurelDomainUnsil::lolos($user->email) ? PeristiwaLogin::DitolakNonaktif : PeristiwaLogin::DitolakDomain;
            $this->catat($peristiwa, $surel, $user);

            return;
        }

        $this->catat(PeristiwaLogin::Gagal, $surel, $user);
        $this->hitungGagal($surel, $user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->catat(PeristiwaLogin::Keluar, $event->user->email, $event->user);
        }
    }

    public function handleLockout(Lockout $event): void
    {
        $this->catat(PeristiwaLogin::Terkunci, Str::lower((string) $event->request->input('data.email', $event->request->input('email', ''))), null);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        /** @var User $user */
        $user = $event->user;
        $this->catat(PeristiwaLogin::ResetKataSandi, $user->email, $user);
    }

    /** Login Google (F2.5) menandai permintaan dengan atribut `metode_login`. */
    private function metode(): MetodeLogin
    {
        $metode = request()->attributes->get('metode_login');

        return $metode instanceof MetodeLogin ? $metode : MetodeLogin::KataSandi;
    }

    private function hitungGagal(string $surel, ?User $user): void
    {
        if ($surel === '') {
            return;
        }

        $kunci = $this->kunciHitung($surel);
        Cache::add($kunci, 0, now()->addMinutes(self::MENIT_JENDELA));
        $jumlah = Cache::increment($kunci);

        if ($user === null || $jumlah < self::BATAS_GAGAL) {
            return;
        }

        Cache::forget($kunci);
        $user->forceFill(['terkunci_sampai' => now()->addMinutes(self::MENIT_KUNCI)])->saveQuietly();
        $this->catat(PeristiwaLogin::Terkunci, $surel, $user);
        $user->notify(new AkunTerkunci((string) request()->ip(), self::MENIT_KUNCI));
    }

    /** @param  array<string, mixed>  $kredensial */
    private function kataSandiBenar(User $user, array $kredensial): bool
    {
        $kataSandi = $kredensial['password'] ?? null;

        return is_string($kataSandi) && $user->password !== null && Hash::check($kataSandi, $user->password);
    }

    private function kunciHitung(string $surel): string
    {
        return 'login-gagal:'.Str::lower($surel);
    }

    private function catat(PeristiwaLogin $peristiwa, string $surel, ?User $user): void
    {
        LogLogin::create([
            'user_id' => $user?->getKey(),
            'email' => Str::lower($surel),
            'peristiwa' => $peristiwa,
            'metode' => $this->metode(),
            'ip' => (string) (request()->ip() ?? '0.0.0.0'),
            'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
            'created_at' => now(),
        ]);
    }
}
