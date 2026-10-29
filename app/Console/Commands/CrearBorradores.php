<?php

namespace App\Console\Commands;

use App\Support\Outlook\DraftGateway;
use App\Support\Outlook\DraftProcessor;
use Illuminate\Console\Command;

class CrearBorradores extends Command
{
    protected $signature = 'despacho:borradores';

    protected $description = 'Crea en el correo del despacho (Microsoft 365) los borradores de las comunicaciones preparadas. Nunca envía.';

    public function handle(DraftGateway $correo, DraftProcessor $procesador): int
    {
        if (! $correo->configurado()) {
            $this->warn('El correo de Microsoft 365 no está configurado (MS_DRAFTS_ENABLED, MS_TENANT_ID, MS_CLIENT_ID, MS_CLIENT_SECRET, MS_MAILBOX). No se hace nada.');

            return self::SUCCESS;
        }
        $hechos = $fallos = 0;
        foreach ($procesador->pendientes()->get() as $d) {
            $r = $procesador->procesar($d);
            $r->status === 'borrador_creado' ? $hechos++ : $fallos++;
        }
        $this->info("Borradores creados: {$hechos}. Con error: {$fallos}.");

        return self::SUCCESS;
    }
}
