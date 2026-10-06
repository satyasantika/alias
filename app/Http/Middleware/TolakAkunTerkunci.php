<?php

namespace App\Http\Middleware;

use App\Enums\PeristiwaLogin;
use App\Models\LogLogin;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** BR-34: sesi akun yang dinonaktifkan atau terkunci diakhiri pada permintaan berikutnya. */
class TolakAkunTerkunci
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && (! $user->aktif || $user->terkunci())) {
            LogLogin::create([
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'peristiwa' => PeristiwaLogin::DitolakNonaktif,
                'ip' => (string) $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);

            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to(Filament::getPanel('alias')->getLoginUrl())
                ->with('status', 'Akun tidak aktif atau sedang dikunci.');
        }

        return $next($request);
    }
}
