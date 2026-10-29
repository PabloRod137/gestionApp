<?php

namespace App\Support;

/**
 * Datos para buscadores y redes sociales de la web pública: ficha local (JSON-LD) y rutas del sitemap.
 * Todo sale de la configuración del despacho (Configuración del panel y config/site.php), no del código.
 * No se publican valoraciones ni estrellas: si las hay, deben venir de la ficha de Google Business Profile.
 */
class Seo
{
    public const IMAGEN = '/assets/og-nortia.png';

    /** «Lunes a viernes, 9:00 – 19:00» -> «Mo-Fr 09:00-19:00»; null si el texto no tiene ese formato. */
    public static function horario(?string $texto): ?string
    {
        if (! preg_match('/lunes a viernes\D+(\d{1,2}):(\d{2})\D+(\d{1,2}):(\d{2})/iu', (string) $texto, $m)) {
            return null;
        }

        return sprintf('Mo-Fr %02d:%02d-%02d:%02d', $m[1], $m[2], $m[3], $m[4]);
    }

    /** Ficha local del despacho (LegalService es un LocalBusiness). Solo incluye datos que existen y no son de relleno. */
    public static function fichaLocal(): array
    {
        $d = Despacho::datos();
        $direccion = config('site.address');

        $ficha = [
            '@context' => 'https://schema.org',
            '@type' => 'LegalService',
            '@id' => url('/').'#despacho',
            'name' => 'Nortia',
            'url' => url('/'),
            'logo' => url('/assets/logo-mark.png'),
            'image' => url(self::IMAGEN),
            'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Cantabria'],
        ];

        if (! Readiness::isPlaceholder($d['telefono'])) {
            $ficha['telephone'] = config('site.phone_tel');
        }
        if (! Readiness::isPlaceholder($d['email'])) {
            $ficha['email'] = $d['email'];
        }
        if (preg_match('/^(.+?),\s*(\d{5})\s+([^,]+),\s*(.+)$/u', (string) $direccion, $m)) {
            $ficha['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $m[1],
                'postalCode' => $m[2],
                'addressLocality' => $m[3],
                'addressRegion' => $m[4],
                'addressCountry' => 'ES',
            ];
        }
        if ($horario = self::horario(config('site.hours'))) {
            $ficha['openingHours'] = $horario;
        }

        return $ficha;
    }

    /** Rutas indexables: páginas fijas, artículos escritos a mano y publicados en el panel. */
    public static function rutasSitemap(): array
    {
        $rutas = ['/', '/quienes-somos', '/servicios', '/inmuebles', '/blog', '/tienda', '/contacto', '/reservar-cita'];
        foreach (glob(resource_path('views/site/blog/*.blade.php')) as $archivo) {
            $rutas[] = '/blog/'.basename($archivo, '.blade.php');
        }
        foreach (\App\Models\BlogPost::where('publicado', true)->pluck('slug') as $slug) {
            $rutas[] = '/blog/'.$slug;
        }

        return array_values(array_unique($rutas));
    }
}
