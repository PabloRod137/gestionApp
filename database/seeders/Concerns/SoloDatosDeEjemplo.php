<?php

namespace Database\Seeders\Concerns;

/** Los seeders de ejemplo crean usuarios con contraseñas conocidas: en producción solo con permiso expreso. */
trait SoloDatosDeEjemplo
{
    protected function exigirDatosDeEjemplo(): void
    {
        if (app()->isProduction() && ! config('app.allow_sample_data')) {
            throw new \RuntimeException('Estos datos de ejemplo traen contraseñas conocidas y no se pueden cargar en producción. Si es una instalación de pruebas, define ALLOW_SAMPLE_DATA=1.');
        }
    }
}
