<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** BR-34: super-admin & admin-alias wajib mengaktifkan MFA sebelum membuka halaman panel lain. */
class WajibMfaAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('alias.mfa_aktif') || ! $user instanceof User || ! $user->wajibMfa() || $user->getAppAuthenticationSecret() !== null) {
            return $next($request);
        }

        $panel = Filament::getCurrentOrDefaultPanel();
        $profil = $panel->getProfileUrl();

        if ($request->routeIs('filament.*.auth.*') || $request->is('livewire*', 'livewire-*/*') || $request->url() === $profil) {
            return $next($request);
        }

        return redirect()->to($profil)->with('mfa_wajib', true);
    }
}
