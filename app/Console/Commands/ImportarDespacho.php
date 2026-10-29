<?php

namespace App\Console\Commands;

use App\Support\Import\ReadModelImporter;
use Illuminate\Console\Command;

class ImportarDespacho extends Command
{
    protected $signature = 'despacho:importar {ruta : Base de lectura del despacho (DESPACHO_READMODEL.db)}';

    protected $description = 'Importa clientes, expedientes, servicios, modelos de documento y comunidades desde la base de lectura del despacho';

    public function handle(ReadModelImporter $importer): int
    {
        $r = $importer->importar($this->argument('ruta'));

        foreach ($r['contadores'] as $tabla => $n) {
            $this->line(str_pad($tabla, 18).$n);
        }
        foreach ($r['avisos'] as $aviso) {
            $this->warn($aviso);
        }
        $this->info('Importacion terminada'.($r['avisos'] ? ' con '.count($r['avisos']).' aviso(s).' : '.'));

        return self::SUCCESS;
    }
}
