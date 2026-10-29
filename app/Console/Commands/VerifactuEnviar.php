<?php

namespace App\Console\Commands;

use App\Support\Verifactu\EnviadorAeat;
use Illuminate\Console\Command;

class VerifactuEnviar extends Command
{
    protected $signature = 'verifactu:enviar {--forzar : Ignora la espera entre envíos}';

    protected $description = 'Envía a la AEAT los registros de facturación pendientes (Veri*Factu). Solo si VERIFACTU_ACTIVO=true';

    public function handle(EnviadorAeat $enviador): int
    {
        $r = $enviador->enviarPendientes((bool) $this->option('forzar'));
        if ($r['mensaje']) {
            $this->warn($r['mensaje']);
        }
        if ($r['enviados']) {
            $this->info("Enviados {$r['enviados']}: {$r['aceptados']} aceptados, {$r['con_errores']} con errores, {$r['rechazados']} rechazados.");
        }

        return self::SUCCESS;
    }
}
