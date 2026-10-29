<?php

/*
 * Arranque de las pruebas. Fija ANTES de que arranque Laravel las variables que aíslan las pruebas del resto del
 * sistema, pisando las que tuviera el terminal (APP_ENV=local, DB_DATABASE=la base real, SESSION_DRIVER=file…).
 * Con una base de datos de verdad en esas variables, RefreshDatabase la borraría. phpunit.xml solo rellena las que
 * faltan; esto las impone. Además, tests/TestCase.php se niega a ejecutar nada si la base no es la de memoria.
 */
require __DIR__.'/../vendor/autoload.php';

foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'SESSION_DRIVER' => 'array',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'ALLOW_SAMPLE_DATA' => 'false',
    'FACTURACION_BORRADORES_AUTOMATICOS' => 'false',   // las pruebas que lo necesitan lo activan con config()
    'FACTURACION_FIN_DE_MES' => 'false',
    'DOCUMENTAL_ACTIVO' => 'false',
    'DOCUMENTAL_RAIZ' => '',
    'DOCUMENTAL_COLA_CONEXION' => 'sync',
] as $nombre => $valor) {
    putenv("$nombre=$valor");
    $_ENV[$nombre] = $valor;
    $_SERVER[$nombre] = $valor;
}
