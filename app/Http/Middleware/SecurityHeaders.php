<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad. Todas las respuestas llevan las básicas; la web pública (modo «csp») añade además una
 * política de contenido estricta: sin scripts ni estilos incrustados, solo recursos propios y el mapa de OpenStreetMap.
 * El panel y el área cliente no la llevan porque sus pantallas usan scripts incrustados (Breeze/Alpine).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($mode === 'csp') {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self'",
                "img-src 'self' data:",
                "connect-src 'self'",
                "frame-src 'self' https://www.openstreetmap.org",
                "form-action 'self'",
                "base-uri 'self'",
                "frame-ancestors 'none'",
            ]));
            // La web no usa estas funciones del navegador: se desactivan también para cualquier contenido incrustado.
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        // La API contiene sesión y datos personales: nunca debe cachearse.
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
