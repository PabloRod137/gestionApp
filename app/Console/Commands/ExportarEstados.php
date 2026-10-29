<?php

namespace App\Console\Commands;

use App\Support\Sync\ExportadorSharePoint;
use Illuminate\Console\Command;

class ExportarEstados extends Command
{
    protected $signature = 'despacho:exportar-estados {--carpeta= : Carpeta de salida (por defecto SYNC_EXPORT_DIR)}';

    protected $description = 'Deja en una carpeta los avances del panel pendientes de llegar a SharePoint, para que los lea Power Automate (no escribe en SharePoint)';

    public function handle(ExportadorSharePoint $exportador): int
    {
        if ($c = $this->option('carpeta')) {
            config(['sync.carpeta_salida' => $c]);
        }
        $r = $exportador->exportar();
        if (! $r) {
            $this->info('No hay cambios pendientes de exportar.');

            return self::SUCCESS;
        }
        $this->info("Exportados {$r['eventos']} cambio(s):");
        $this->line('  '.$r['json']);
        $this->line('  '.$r['csv']);

        return self::SUCCESS;
    }
}
