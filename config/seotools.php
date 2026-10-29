<?php
/**
 * @see https://github.com/artesaos/seotools
 */

return [
    'inertia' => env('SEO_TOOLS_INERTIA', false),
    'meta' => [
        /*
         * The default configurations to be used by the meta generator.
         */
        'defaults'       => [
            'title'        => "Gestoría y Asesoría en Santander | Nortia — Autónomos, Pymes y Particulares", // set false to total remove
            'titleBefore'  => false, // Put defaults.title before page title, like 'It's Over 9000! - Dashboard'
            'description'  => "Nortia es tu gestoría y asesoría en Santander. Gestión de autónomos, declaración de la renta, nóminas, Seguridad Social, herencias y trámites con la Administración. Pide cita.", // set false to total remove
            'separator'    => ' | ',
            'keywords'     => ['gestoria santander', 'asesoria santander', 'autonomos santander', 'renta santander', 'nortia', 'asesoria fiscal cantabria', 'gestoria pymes cantabria'],
            'canonical'    => 'current', // Set to null or 'full' to use Url::full(), set to 'current' to use Url::current(), set false to total remove
            'robots'       => 'index, follow', // Set to 'all', 'none' or any combination of index/noindex and follow/nofollow
        ],
        /*
         * Webmaster tags are always added.
         */
        'webmaster_tags' => [
            'google'    => null,
            'bing'      => null,
            'alexa'     => null,
            'pinterest' => null,
            'yandex'    => null,
            'norton'    => null,
        ],

        'add_notranslate_class' => false,
    ],
    'opengraph' => [
        /*
         * The default configurations to be used by the opengraph generator.
         */
        'defaults' => [
            'title'       => 'Gestoría y Asesoría en Santander | Nortia — Autónomos, Pymes y Particulares', // set false to total remove
            'description' => 'Nortia es tu gestoría y asesoría en Santander. Gestión de autónomos, declaración de la renta, nóminas, Seguridad Social, herencias y trámites con la Administración. Pide cita.', // set false to total remove
            'url'         => null, // Set null for using Url::current(), set false to total remove
            'type'        => 'website',
            'site_name'   => 'Nortia',
            'images'      => [],
        ],
    ],
    'twitter' => [
        /*
         * The default values to be used by the twitter cards generator.
         */
        'defaults' => [
            'card'        => 'summary_large_image',
            //'site'        => '@Nortia',
        ],
    ],
    'json-ld' => [
        /*
         * The default configurations to be used by the json-ld generator.
         */
        'defaults' => [
            'title'       => 'Gestoría y Asesoría en Santander | Nortia — Autónomos, Pymes y Particulares', // set false to total remove
            'description' => 'Nortia es tu gestoría y asesoría en Santander. Gestión de autónomos, declaración de la renta, nóminas, Seguridad Social, herencias y trámites con la Administración. Pide cita.', // set false to total remove
            'url'         => 'current', // Set to null or 'full' to use Url::full(), set to 'current' to use Url::current(), set false to total remove
            'type'        => 'WebSite',
            'images'      => [],
        ],
    ],
];
