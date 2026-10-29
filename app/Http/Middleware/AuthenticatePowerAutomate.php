<?php
// app/Http/Middleware/AuthenticatePowerAutomate.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autenticación exclusivo para el webhook de Power Automate.
 *
 * Valida que la petición entrante incluya el Bearer token correcto
 * definido en el archivo .env como POWER_AUTOMATE_API_TOKEN.
 *
 * En entorno local (APP_ENV=local) se omite la validación del token si
 * POWER_AUTOMATE_API_TOKEN no está definido, para facilitar pruebas.
 */
class AuthenticatePowerAutomate
{
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener el token esperado desde la configuración del servicio
        $expectedToken = config('services.power_automate.api_token');

        // Si no hay token configurado en producción, rechazar y alertar
        if (empty($expectedToken) && app()->isProduction()) {
            Log::critical(
                'PowerAutomate Middleware: POWER_AUTOMATE_API_TOKEN no configurado en producción. ' .
                'Bloqueando todas las peticiones entrantes.'
            );
            return response()->json([
                'error' => 'Servicio no disponible. Contacte con el administrador.',
            ], 503);
        }

        // En local sin token configurado: loguear aviso pero dejar pasar (desarrollo)
        if (empty($expectedToken) && app()->isLocal()) {
            Log::warning(
                'PowerAutomate Middleware [LOCAL]: POWER_AUTOMATE_API_TOKEN no configurado. ' .
                'Petición aceptada en modo desarrollo sin validación.'
            );
            return $next($request);
        }

        // Extraer el token del encabezado: Authorization: Bearer {token}
        $providedToken = $request->bearerToken();

        // Comparar de forma segura con hash_equals() para prevenir timing attacks.
        // Un atacante que mida el tiempo de respuesta no puede deducir si el token
        // es "casi correcto" porque hash_equals() siempre tarda el mismo tiempo.
        if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            Log::warning('PowerAutomate Middleware: Petición rechazada. Token inválido o ausente.', [
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method'     => $request->method(),
                'path'       => $request->path(),
            ]);

            return response()->json([
                'error' => 'No autorizado. Token Bearer requerido.',
            ], 401);
        }

        // Token válido: dejar pasar la petición al controlador
        return $next($request);
    }
}
