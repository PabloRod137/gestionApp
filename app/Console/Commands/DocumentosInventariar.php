<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Support\Documental\Inventario;
use Illuminate\Console\Command;

class DocumentosInventariar extends Command
{
    protected $signature = 'documentos:inventariar {--cliente= : Código del cliente (por defecto, todos)} {--sin-cola : Solo inventaría, no manda nada a la cola}';

    protected $description = 'Recorre las carpetas de los clientes, da de alta lo nuevo o modificado y lo manda a la cola local de procesado';

    public function handle(Inventario $inventario): int
    {
        if (! config('documental.raiz') || ! is_dir((string) config('documental.raiz'))) {
            $this->warn('No hay carpeta raíz documental (DOCUMENTAL_RAIZ): no hay nada que inventariar.');

            return self::SUCCESS;
        }
        $cliente = null;
        if ($codigo = $this->option('cliente')) {
            $cliente = Client::where('codigo', $codigo)->first();
            if (! $cliente) {
                $this->error("No existe el cliente $codigo.");

                return self::FAILURE;
            }
        }
        $r = $inventario->inventariar($cliente, ! $this->option('sin-cola'));
        $this->info("Clientes: {$r['clientes']} · nuevos: {$r['nuevos']} · modificados: {$r['modificados']} · omitidos: {$r['omitidos']} · desaparecidos: {$r['desaparecidos']} · encolados: {$r['encolados']}");

        return self::SUCCESS;
    }
}
