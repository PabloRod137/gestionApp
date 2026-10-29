<?php

namespace App\Support\Documental;

use App\Jobs\ProcesarDocumentoIndexado;
use App\Models\Client;
use App\Models\DocumentoIndexado;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Inventario de los documentos de las carpetas de los clientes. Recorre solo la carpeta de cada cliente (nunca fuera de
 * ella), da de alta lo nuevo, detecta lo modificado (por huella) y marca lo desaparecido. Lo pendiente se manda a la
 * cola local de procesado. Es el barrido que cubre también lo creado fuera del panel.
 */
class Inventario
{
    /** @return array{clientes:int,nuevos:int,modificados:int,omitidos:int,desaparecidos:int,encolados:int} */
    public function inventariar(?Client $solo = null, bool $encolar = true): array
    {
        $r = ['clientes' => 0, 'nuevos' => 0, 'modificados' => 0, 'omitidos' => 0, 'desaparecidos' => 0, 'encolados' => 0];
        $raiz = config('documental.raiz');
        if (! $raiz || ! is_dir($raiz)) {
            return $r;
        }
        $rootReal = realpath($raiz);

        $clientes = $solo ? collect([$solo]) : Client::whereNotNull('ruta_sharepoint')->where('ruta_sharepoint', '!=', '')->get();
        foreach ($clientes as $cliente) {
            $carpeta = CarpetaCliente::de($cliente);
            if (! $carpeta) {
                continue;
            }
            $r['clientes']++;
            $vistos = [];
            $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS));
            foreach ($iterador as $f) {
                if (! $f->isFile() || $f->isLink() || str_starts_with($f->getFilename(), '~$') || str_starts_with($f->getFilename(), '.')) {
                    continue;
                }
                $real = $f->getRealPath();
                if (! $real || ! str_starts_with($real, $carpeta.DIRECTORY_SEPARATOR)) {
                    continue;   // un enlace o ruta que sale de la carpeta del cliente
                }
                $rel = str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($real, strlen($rootReal)), '\\/'));
                $ext = strtolower($f->getExtension());
                $vistos[] = $rel;
                $this->registrar($cliente, $rel, $f->getFilename(), $ext, $real, $r);
            }

            // lo que estaba en el inventario y ya no está
            foreach (DocumentoIndexado::where('client_id', $cliente->id)->whereNotIn('estado', ['desaparecido'])->whereNotIn('ruta', $vistos)->get() as $d) {
                $d->update(['estado' => 'desaparecido', 'motivo' => 'El archivo ya no está en la carpeta del cliente.']);
                $r['desaparecidos']++;
            }
        }

        if ($encolar) {
            $r['encolados'] = $this->encolar();
        }

        return $r;
    }

    /** Manda a la cola local los documentos pendientes (y los que fallaron, hasta el máximo de reintentos). */
    public function encolar(): int
    {
        $n = 0;
        $consulta = DocumentoIndexado::where(fn ($q) => $q->where('estado', 'pendiente')->orWhere(fn ($q) => $q->where('estado', 'error')->where('intentos', '<', (int) config('documental.reintentos', 3))))
            ->orderBy('id')->limit((int) config('documental.lote', 200))->get();
        foreach ($consulta as $d) {
            ProcesarDocumentoIndexado::dispatch($d->id)->onConnection((string) config('documental.conexion', 'database'))->onQueue((string) config('documental.cola', 'documentos'));
            $n++;
        }

        return $n;
    }

    private function registrar(Client $cliente, string $rel, string $nombre, string $ext, string $real, array &$r): void
    {
        $tamano = (int) filesize($real);
        $mtime = (int) filemtime($real);
        $d = DocumentoIndexado::where('ruta', $rel)->first();

        // formatos que no se procesan: se anotan con su motivo
        $omitida = config('documental.omitidas.'.$ext);
        if ($omitida || ! in_array($ext, config('documental.extensiones', []), true) || $tamano > (int) config('documental.max_bytes')) {
            $motivo = $omitida ?: (! in_array($ext, config('documental.extensiones', []), true) ? 'Formato no admitido.' : 'Archivo demasiado grande para procesarlo.');
            if (! $d) {
                DocumentoIndexado::create(['client_id' => $cliente->id, 'ruta' => $rel, 'nombre' => $nombre, 'extension' => mb_substr($ext, 0, 8), 'tamano' => $tamano, 'modificado_ts' => $mtime, 'estado' => 'omitido', 'motivo' => $motivo]);
                $r['omitidos']++;
            }

            return;
        }

        if ($d && $d->modificado_ts === $mtime && $d->tamano === $tamano && $d->estado !== 'desaparecido') {
            return;   // sin cambios
        }

        $sha = hash_file('sha256', $real);
        if (! $d) {
            DocumentoIndexado::create(['client_id' => $cliente->id, 'ruta' => $rel, 'nombre' => $nombre, 'extension' => $ext, 'tamano' => $tamano, 'sha256' => $sha, 'modificado_ts' => $mtime, 'estado' => 'pendiente']);
            $r['nuevos']++;

            return;
        }
        if ($d->sha256 !== $sha || $d->estado === 'desaparecido') {
            $d->update(['client_id' => $cliente->id, 'tamano' => $tamano, 'sha256' => $sha, 'modificado_ts' => $mtime, 'estado' => 'pendiente', 'motivo' => null, 'intentos' => 0]);
            $r['modificados']++;
        } else {
            $d->update(['tamano' => $tamano, 'modificado_ts' => $mtime]);   // solo cambió la fecha: mismo contenido
        }
    }
}
