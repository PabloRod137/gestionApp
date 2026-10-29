<?php

namespace App\Support\Documental;

use App\Models\DocumentoIndexado;
use App\Models\Hallazgo;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Procesa UN documento del inventario: comprueba que sigue dentro de la carpeta de SU cliente, lee el texto en local,
 * busca propuestas con reglas y las guarda como hallazgos pendientes. Solo mira el archivo y el cliente a los que
 * pertenece; nunca consulta a otros clientes.
 */
class Procesador
{
    public function __construct(private readonly Extractor $extractor)
    {
    }

    public function procesar(DocumentoIndexado $d): DocumentoIndexado
    {
        $d->loadMissing('client');
        $carpeta = CarpetaCliente::de($d->client);
        $abs = $carpeta ? realpath(rtrim((string) config('documental.raiz'), '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $d->ruta)) : false;

        // El archivo debe seguir existiendo y estar dentro de la carpeta de su cliente (nada de enlaces ni rutas que salgan de ella)
        if (! $abs || ! str_starts_with($abs, $carpeta.DIRECTORY_SEPARATOR)) {
            $d->update(['estado' => 'desaparecido', 'motivo' => 'El archivo ya no está en la carpeta del cliente.']);

            return $d;
        }

        $d->update(['estado' => 'procesando']);
        try {
            $lectura = $this->extractor->extraer($abs, $d->extension);
        } catch (ExtraccionException $e) {
            $d->update(['estado' => 'omitido', 'motivo' => mb_substr($e->getMessage(), 0, 300), 'texto' => null]);

            return $d;
        } catch (Throwable $e) {
            $d->update(['estado' => 'error', 'motivo' => mb_substr($e->getMessage(), 0, 300), 'intentos' => $d->intentos + 1]);

            return $d;
        }

        DB::transaction(function () use ($d, $lectura) {
            // un archivo que cambió deja obsoletas las propuestas pendientes de su versión anterior
            Hallazgo::where('documento_id', $d->id)->where('estado', 'pendiente')->where('sha256', '!=', (string) $d->sha256)->update(['estado' => 'obsoleto']);

            $d->update([
                'estado' => 'analizado', 'motivo' => null, 'metodo' => $lectura['metodo'], 'paginas' => count($lectura['paginas']),
                'texto' => implode("\n\n", $lectura['paginas']), 'analizado_at' => now(),
            ]);
            foreach (DetectorDatos::detectar($d->client, $lectura['paginas']) as $h) {
                Hallazgo::firstOrCreate(
                    ['documento_id' => $d->id, 'campo' => $h['campo'], 'valor' => $h['valor']],
                    $h + ['client_id' => $d->client_id, 'metodo' => 'regla', 'sha256' => $d->sha256],
                );
            }
        });

        return $d->fresh();
    }
}
