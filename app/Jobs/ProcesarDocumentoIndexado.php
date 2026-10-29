<?php

namespace App\Jobs;

use App\Models\DocumentoIndexado;
use App\Support\Documental\Procesador;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Procesa un documento del inventario en la cola local (lectura de texto y propuestas). Solo ve ese archivo y ese cliente. */
class ProcesarDocumentoIndexado implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public int $documentoId)
    {
    }

    public function handle(Procesador $procesador): void
    {
        $d = DocumentoIndexado::find($this->documentoId);
        if (! $d || ! in_array($d->estado, ['pendiente', 'error'], true)) {
            return;   // ya procesado o borrado
        }
        $procesador->procesar($d);
    }
}
