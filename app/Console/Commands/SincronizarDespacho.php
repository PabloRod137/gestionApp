<?php

namespace App\Console\Commands;

use App\Support\Import\SincronizacionWordVivo;
use Illuminate\Console\Command;

class SincronizarDespacho extends Command
{
    protected $signature = 'despacho:sincronizar {carpeta : Carpeta WORD_VIVO_SYNC con los CSV de Power Automate}';

    protected $description = 'Sincroniza expedientes, registro de documentos y tarifa desde los CSV de Power Automate (no duplica ni pisa servicios asignados)';

    public function handle(SincronizacionWordVivo $sync): int
    {
        try {
            $r = $sync->sincronizar($this->argument('carpeta'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        foreach ($r['contadores'] as $tabla => $n) {
            $this->line(str_pad($tabla, 18).$n);
        }
        foreach ($r['avisos'] as $aviso) {
            $this->warn($aviso);
        }
        \App\Models\Setting::setValue('readmodel_imported_at', now()->toIso8601String());
        $this->info('Sincronización terminada'.($r['avisos'] ? ' con '.count($r['avisos']).' aviso(s).' : '.'));

        return self::SUCCESS;
    }
}
