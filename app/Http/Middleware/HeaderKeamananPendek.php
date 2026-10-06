<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Header keamanan untuk wajah pengalihan publik (BR-09, BR-27). Tanpa sesi/cookie. */
class HeaderKeamananPendek
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', HeaderKeamananPublik::CSP);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Pengalihan sukses: situs tujuan dapat mengetahui bahwa pengunjung datang dari Alias (BR-09, ¹).
        if ($response->isRedirection()) {
            $response->headers->set('Referrer-Policy', 'unsafe-url');
        } else {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }
}
