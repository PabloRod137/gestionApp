<?php

namespace App\Support;

/**
 * ¿Está la web lista para publicarse? Devuelve la lista de bloqueos (cada uno con una clave estable y un texto)
 * para que nadie publique con datos legales de relleno, el modo de depuración encendido o claves de prueba.
 */
class Readiness
{
    /** Un valor vacío o de relleno («PENDIENTE…», «TODO», «EJEMPLO», MARCADOR_EN_MAYUSCULAS) no es un dato legal. */
    public static function isPlaceholder(?string $value): bool
    {
        $text = trim((string) $value);

        return $text === ''
            || preg_match('/pendiente|todo|cambiar|example|ejemplo|placeholder|changeme|lorem/i', $text)
            || preg_match('/^[A-Z0-9]+(_[A-Z0-9]+)+$/', $text);
    }

    /** Nombres de las variables legales y de privacidad que faltan. */
    public static function missingLegalData(): array
    {
        return collect(config('legal'))
            ->filter(fn ($value) => self::isPlaceholder($value))
            ->keys()->map(fn ($key) => strtoupper($key))->values()->all();
    }

    /** @return array<string, string> clave => explicación */
    public static function blockers(): array
    {
        $blockers = [];
        foreach (self::missingLegalData() as $name) {
            $blockers["legal:$name"] = "Falta el dato legal $name (variable de entorno del mismo nombre).";
        }

        // el teléfono y el correo de la web no pueden ser los de relleno (valores por defecto de config/site.php) ni estar vacíos
        $telefono = preg_replace('/\D/', '', (string) config('site.phone'));
        if (self::isPlaceholder(config('site.phone')) || str_ends_with($telefono, '600000000')) {
            $blockers['site:SITE_PHONE'] = 'SITE_PHONE es el de relleno o está vacío: indica el teléfono del despacho.';
        }
        if (self::isPlaceholder(config('site.email')) || str_contains(strtolower((string) config('site.email')), 'nortia-demo.test')) {
            $blockers['site:SITE_EMAIL'] = 'SITE_EMAIL es el de relleno o está vacío: indica el correo del despacho.';
        }

        if (config('app.env') === 'production') {
            if (config('app.debug')) {
                $blockers['app:debug'] = 'APP_DEBUG está activo en producción: muestra errores y datos internos a los visitantes.';
            }
            if (! str_starts_with((string) config('app.url'), 'https://')) {
                $blockers['app:url'] = 'APP_URL debe empezar por https:// en producción.';
            }
            if (in_array(config('mail.default'), ['log', 'array'], true)) {
                $blockers['mail:driver'] = 'MAIL_MAILER no envía correos reales: los avisos de contacto y citas no llegarían al despacho.';
            }
        }

        if (config('shop.payments.enabled')) {
            $stripe = config('shop.payments.stripe');
            if (config('shop.payments.provider') !== 'stripe') {
                $blockers['payments:provider'] = 'PAYMENTS_PROVIDER debe ser «stripe» para activar los pagos.';
            }
            if (! $stripe['secret_key']) {
                $blockers['payments:STRIPE_SECRET_KEY'] = 'Falta STRIPE_SECRET_KEY.';
            }
            if (! $stripe['webhook_secret']) {
                $blockers['payments:STRIPE_WEBHOOK_SECRET'] = 'Falta STRIPE_WEBHOOK_SECRET (la firma del webhook de Stripe).';
            }
            if ($stripe['secret_key']) {
                $live = str_starts_with($stripe['secret_key'], 'sk_live_');
                if (config('app.env') === 'production' && ! $live) {
                    $blockers['payments:test_key_in_production'] = 'Hay una clave de prueba de Stripe en producción: los cobros no serían reales.';
                }
                if (config('app.env') !== 'production' && $live) {
                    $blockers['payments:live_key_outside_production'] = 'Hay una clave real de Stripe fuera de producción: podrías cobrar de verdad en pruebas.';
                }
            }
        }

        return $blockers;
    }
}
