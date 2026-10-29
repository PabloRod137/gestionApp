<?php

namespace App\Support\Sync;

use App\Models\SyncEvent;

/**
 * Deja los eventos pendientes en una carpeta (JSON y CSV con las columnas de la lista «Registro_Actividad_Expediente»)
 * para que un flujo de Power Automate los lea. Escribe primero un temporal y lo renombra: el flujo nunca ve un archivo a medias.
 */
class ExportadorSharePoint
{
    private const COLUMNAS = ['ID_Evento', 'Codigo_Expediente', 'Actividad', 'Tipo_Actividad', 'Descripcion', 'Actor', 'Origen', 'FechaHora_Actividad', 'Documento_Referencia', 'Estado_Anterior', 'Estado_Nuevo'];

    private const COLUMNAS_CLIENTES = ['ID_Evento', 'Codigo_Cliente', 'NIF', 'Email', 'Telefono', 'Direccion', 'Actor', 'Origen', 'FechaHora_Actividad'];

    public function carpeta(): string
    {
        $c = (string) config('sync.carpeta_salida');

        return $c !== '' ? $c : storage_path('app/sincronizacion/salida');
    }

    /** @return array{eventos: int, clientes: int, json: ?string, csv: ?string, json_clientes: ?string, csv_clientes: ?string}|null null si no había nada pendiente */
    public function exportar(int $limite = 500): ?array
    {
        $todos = SyncEvent::with('expedient', 'client')->where('estado', 'pendiente')->orderBy('id')->limit($limite)->get();
        if ($todos->isEmpty()) {
            return null;
        }
        $carpeta = $this->carpeta();
        if (! is_dir($carpeta) && ! mkdir($carpeta, 0775, true) && ! is_dir($carpeta)) {
            throw new \RuntimeException("No se pudo crear la carpeta de salida: {$carpeta}");
        }
        [$deClientes, $eventos] = $todos->partition(fn ($e) => $e->esDeCliente());
        $sello = now()->format('Ymd_His');
        $r = ['eventos' => $eventos->count(), 'clientes' => $deClientes->count(), 'json' => null, 'csv' => null, 'json_clientes' => null, 'csv_clientes' => null];

        if ($eventos->isNotEmpty()) {
            $filas = $eventos->map->comoFila()->all();
            $base = $carpeta.DIRECTORY_SEPARATOR.'Nortia_actividad_'.$sello.'_'.$eventos->first()->evento_id;
            $this->escribir($base.'.json', $this->json($filas, 'eventos'));
            $this->escribir($base.'.csv', "\xEF\xBB\xBF".$this->csv($filas, self::COLUMNAS));
            PuenteSharePoint::marcarExportados($eventos, basename($base).'.json');
            [$r['json'], $r['csv']] = [$base.'.json', $base.'.csv'];
        }
        if ($deClientes->isNotEmpty()) {
            $filas = $deClientes->map->comoFilaCliente()->all();
            $base = $carpeta.DIRECTORY_SEPARATOR.'Nortia_clientes_'.$sello.'_'.$deClientes->first()->evento_id;
            $this->escribir($base.'.json', $this->json($filas, 'clientes'));
            $this->escribir($base.'.csv', "\xEF\xBB\xBF".$this->csv($filas, self::COLUMNAS_CLIENTES));
            PuenteSharePoint::marcarExportados($deClientes, basename($base).'.json');
            [$r['json_clientes'], $r['csv_clientes']] = [$base.'.json', $base.'.csv'];
        }

        return $r;
    }

    private function json(array $filas, string $clave): string
    {
        return json_encode(['generado' => now()->toIso8601String(), 'origen' => 'Nortia', $clave => $filas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function escribir(string $destino, string $contenido): void
    {
        $tmp = $destino.'.tmp';
        file_put_contents($tmp, $contenido);
        rename($tmp, $destino);
    }

    private function csv(array $filas, array $columnas): string
    {
        $f = fopen('php://temp', 'w+');
        fputcsv($f, $columnas, ',', '"', '\\', "\r\n");
        foreach ($filas as $fila) {
            fputcsv($f, array_map(fn ($c) => $fila[$c] ?? '', $columnas), ',', '"', '\\', "\r\n");
        }
        rewind($f);

        return stream_get_contents($f);
    }
}
