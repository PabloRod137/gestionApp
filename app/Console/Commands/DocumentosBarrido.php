<?php

namespace App\Console\Commands;

use App\Models\DocumentoIndexado;
use App\Support\Documental\Inventario;
use App\Support\Documental\Procesador;
use Illuminate\Console\Command;

class DocumentosBarrido extends Command
{
    protected $signature = 'documentos:barrido {--limite=200 : Máximo de documentos a procesar en esta pasada}';

    protected $description = 'Barrido nocturno: inventaría las carpetas (también lo creado fuera del panel) y procesa lo pendiente, sin cola, hasta el límite';

    public function handle(Inventario $inventario, Procesador $procesador): int
    {
        if (! config('documental.raiz') || ! is_dir((string) config('documental.raiz'))) {
            $this->warn('No hay carpeta raíz documental (DOCUMENTAL_RAIZ).');

            return self::SUCCESS;
        }
        $r = $inventario->inventariar(null, false);
        $hechos = 0;
        $reintentos = (int) config('documental.reintentos', 3);
        $pendientes = DocumentoIndexado::where(fn ($q) => $q->where('estado', 'pendiente')->orWhere(fn ($q) => $q->where('estado', 'error')->where('intentos', '<', $reintentos)))
            ->orderBy('id')->limit((int) $this->option('limite'))->get();
        foreach ($pendientes as $d) {
            $procesador->procesar($d);
            $hechos++;
        }
        $quedan = DocumentoIndexado::whereIn('estado', ['pendiente', 'error'])->count();
        $this->info("Inventario: {$r['nuevos']} nuevos, {$r['modificados']} modificados, {$r['desaparecidos']} desaparecidos. Procesados: $hechos. Pendientes: $quedan (continúan en la próxima pasada).");

        return self::SUCCESS;
    }
}
