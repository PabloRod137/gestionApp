<?php

namespace App\Support\Import;

use App\Models\Client;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sincroniza los CSV que deja Power Automate en la carpeta WORD_VIVO_SYNC: expedientes, registro de documentos y tarifa.
 * Se puede repetir (cada fila se identifica por su código). No pisa un servicio ya asignado ni borra datos: solo crea y completa.
 * Los CSV llegan en codificación Windows y con el servicio como JSON de Power Automate; aquí se normaliza.
 */
class SincronizacionWordVivo
{
    /** @var array<int, string> */
    private array $avisos = [];

    /** @return array{contadores: array<string,int>, avisos: array<int,string>} */
    public function sincronizar(string $carpeta): array
    {
        if (! is_dir($carpeta)) {
            throw new \InvalidArgumentException("No existe la carpeta: {$carpeta}");
        }
        $this->avisos = [];
        $c = ['servicios_tarifa' => 0, 'expedientes' => 0, 'documentos' => 0, 'eventos_confirmados' => 0];

        DB::transaction(function () use ($carpeta, &$c) {
            if ($filas = $this->leer($carpeta, 'servicios_tarifa_word_vivo.csv')) {
                $c['servicios_tarifa'] = $this->tarifa($filas);
            }
            if ($filas = $this->leer($carpeta, 'expedientes_word_vivo.csv')) {
                $c['expedientes'] = $this->expedientes($filas);
            }
            if ($filas = $this->leer($carpeta, 'Registro_documentos.csv')) {
                $c['documentos'] = $this->documentos($filas);
            }
            // los eventos que Nortia dejó y que SharePoint ya muestra (su ID_Evento) quedan confirmados
            if ($filas = $this->leer($carpeta, 'Registro_Actividad_Expediente.csv')) {
                $c['eventos_confirmados'] = \App\Support\Sync\PuenteSharePoint::confirmar(array_values(array_filter(array_column($filas, 'ID_Evento'), fn ($id) => str_starts_with((string) $id, 'LG-'))));
            }
        });

        return ['contadores' => $c, 'avisos' => $this->avisos];
    }

    /** @return array<int, array<string,string>> */
    private function leer(string $carpeta, string $nombre): array
    {
        $ruta = rtrim($carpeta, '/\\').DIRECTORY_SEPARATOR.$nombre;
        if (! is_file($ruta)) {
            return [];
        }
        $texto = (string) file_get_contents($ruta);
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
        if (! mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }
        $f = fopen('php://temp', 'w+');
        fwrite($f, $texto);
        rewind($f);
        $cab = fgetcsv($f, 0, ',', '"', '\\') ?: [];
        $filas = [];
        while (($r = fgetcsv($f, 0, ',', '"', '\\')) !== false) {
            if (count($r) !== count($cab)) {
                continue;
            }
            $filas[] = array_map(fn ($v) => trim((string) $v), array_combine($cab, $r));
        }
        fclose($f);

        return $filas;
    }

    private function vacio(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    private function clave(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }

    private function estado(?string $e): string
    {
        return match ($this->clave((string) $e)) {
            'en curso' => 'en_proceso',
            'completado' => 'finalizado',
            default => 'abierto',
        };
    }

    /** El servicio puede venir como nombre o como el JSON de Power Automate {"Id":..,"Value":"nombre"}. */
    private function nombreServicio(string $crudo): ?string
    {
        if ($crudo === '') {
            return null;
        }
        if (str_starts_with($crudo, '{')) {
            $j = json_decode($crudo, true);

            return is_array($j) ? $this->vacio($j['Value'] ?? null) : null;
        }

        return $crudo;
    }

    private function tarifa(array $filas): int
    {
        $n = 0;
        foreach ($filas as $r) {
            $s = Service::where('codigo', $r['CODIGO_SERVICIO'] ?? '')->first();
            if (! $s) {
                if (($r['CODIGO_SERVICIO'] ?? '') !== '') {
                    $this->avisos[] = "Tarifa: el servicio {$r['CODIGO_SERVICIO']} no existe en el programa";
                }

                continue;
            }
            $s->update([
                'palabras_clave' => $this->vacio($r['PALABRAS_CLAVE_ASUNTO'] ?? null),
                'notas' => $this->vacio($r['NOTAS'] ?? null),
                'tipo_servicio' => $this->vacio($r['Tipo_de_servicio'] ?? null),
            ]);
            $n++;
        }

        return $n;
    }

    private function expedientes(array $filas): int
    {
        $clientes = Client::pluck('id', 'codigo');
        $servicios = Service::all()->mapWithKeys(fn ($s) => [$this->clave($s->nombre) => $s->id]);
        $n = 0;
        foreach ($filas as $r) {
            $codigo = $r['EXPEDIENTE_ID'] ?? '';
            $clienteId = $clientes[$r['CLIENTE_NUMERO'] ?? ''] ?? null;
            if ($codigo === '' || ! $clienteId) {
                $this->avisos[] = "Expediente {$codigo}: cliente {$r['CLIENTE_NUMERO']} no encontrado; no se importa";

                continue;
            }
            $nombre = $this->nombreServicio($r['SERVICIO'] ?? '');
            $servicioId = $nombre ? ($servicios[$this->clave($nombre)] ?? null) : null;
            if ($nombre && ! $servicioId) {
                $this->avisos[] = "Expediente {$codigo}: servicio «{$nombre}» desconocido";
            }

            $e = Expedient::withTrashed()->firstOrNew(['codigo' => $codigo]);
            $e->client_id = $e->client_id ?: $clienteId;
            $e->titulo = $this->vacio($r['ASUNTO'] ?? null) ?: ($e->titulo ?: 'Expediente '.$codigo);
            $e->estado = $this->estado($r['ESTADO'] ?? null);
            $e->ruta_sharepoint = $this->vacio($r['RUTA_SHAREPOINT'] ?? null) ?: $e->ruta_sharepoint;
            // un servicio ya asignado nunca se pisa; solo se completa si estaba vacío
            $e->service_id = $e->service_id ?: $servicioId;
            $e->save();
            $n++;
        }

        return $n;
    }

    private function documentos(array $filas): int
    {
        $exp = Expedient::pluck('id', 'codigo');
        $clientOf = Expedient::pluck('client_id', 'codigo');
        $n = 0;
        foreach ($filas as $r) {
            $codigo = $r['Codigo_expediente'] ?? '';
            if (! isset($exp[$codigo]) || ($r['Documento_ID'] ?? '') === '') {
                $this->avisos[] = "Documento {$r['Documento_ID']}: expediente {$codigo} no encontrado; no se importa";

                continue;
            }
            ExpedientDocument::withTrashed()->updateOrCreate(['codigo_origen' => $r['Documento_ID']], [
                'expedient_id' => $exp[$codigo],
                'client_id' => $clientOf[$codigo],
                'nombre' => $r['Nombre_documento'] ?: $r['Documento_ID'],
                'ruta' => null,
                'url_externa' => $this->vacio($r['Ruta_URL'] ?? null),
                'tipo_documento' => $this->vacio($r['Tipo_documento'] ?? null),
                'origen' => $this->vacio($r['Origen'] ?? null),
                'visibilidad' => 'interno',
                'canal_entrada' => 'sharepoint',
            ]);
            $n++;
        }

        return $n;
    }
}
