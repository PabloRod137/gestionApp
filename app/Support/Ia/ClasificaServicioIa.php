<?php

namespace App\Support\Ia;

use App\Models\Expedient;
use App\Models\Service;
use App\Support\ClasificadorServicio;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cuando las palabras clave no bastan, el modelo local elige el servicio de un expediente SOLO entre una lista cerrada.
 * Recibe únicamente el asunto, el tipo de cliente y el catálogo de servicios: nunca datos personales ni de otros clientes.
 * La respuesta es una sugerencia que una persona confirma.
 */
class ClasificaServicioIa
{
    private const SISTEMA = 'Eres un clasificador de expedientes de una asesoría. Elige el servicio que mejor corresponde al asunto, SOLO entre los '
        .'servicios recibidos. Responde solo JSON con las claves codigo_servicio y motivo (una frase). Si ninguno encaja con claridad, '
        .'codigo_servicio debe ser "NINGUNO". No inventes códigos.';

    public function __construct(private ClasificadorServicio $clasificador) {}

    /** @return array{id: int, codigo: string, nombre: string, motivo: string, modelo: string}|null */
    public function sugerir(Expedient $e): ?array
    {
        if (! config('ia.enabled')) {
            throw new IaException('La IA local no está activada en este servidor.');
        }
        $e->loadMissing('client');
        $servicios = $this->candidatos($e);
        $payload = [
            'asunto' => (string) $e->titulo,
            'tipo_cliente' => (string) $e->client->tipo,
            'servicios' => $servicios->map(fn (Service $s) => [
                'codigo_servicio' => $s->codigo, 'nombre' => $s->nombre,
                'palabras_clave' => Str::limit((string) $s->palabras_clave, 80, ''), 'accion' => Str::limit((string) $s->accion_sugerida, 90, ''),
            ])->values()->all(),
        ];
        $modelo = (string) config('ia.ollama.model');
        try {
            $r = Http::timeout((int) config('ia.ollama.timeout'))->post(rtrim((string) config('ia.ollama.url'), '/').'/api/chat', [
                'model' => $modelo, 'stream' => false, 'think' => false, 'format' => 'json',
                'options' => ['temperature' => 0, 'num_ctx' => 4096, 'num_predict' => 120],
                'messages' => [['role' => 'system', 'content' => self::SISTEMA], ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]],
            ]);
        } catch (ConnectionException) {
            throw new IaException('El modelo local no responde. Comprueba que Ollama está arrancado en el equipo del despacho.');
        }
        if (! $r->successful()) {
            throw new IaException('El modelo local no responde ('.$r->status().').');
        }
        $json = json_decode((string) $r->json('message.content'), true);
        if (! is_array($json)) {
            return null;
        }
        // solo vale un código de la lista que se le dio
        $elegido = $servicios->firstWhere('codigo', (string) ($json['codigo_servicio'] ?? ''));

        return $elegido ? ['id' => $elegido->id, 'codigo' => $elegido->codigo, 'nombre' => $elegido->nombre, 'motivo' => mb_substr((string) ($json['motivo'] ?? ''), 0, 300), 'modelo' => $modelo] : null;
    }

    /** Los mejores candidatos por palabras clave, completados con los servicios del mismo tipo de cliente. */
    private function candidatos(Expedient $e, int $max = 20)
    {
        $porClave = collect($this->clasificador->sugerir((string) $e->titulo, $e->id, 12))->pluck('servicio');
        $tipo = Str::of((string) $e->client->tipo)->ascii()->lower()->toString();
        $relleno = Service::where('activo', true)->whereNotIn('id', $porClave->pluck('id'))->orderBy('nombre')->get()
            ->filter(fn ($s) => in_array(Str::of((string) $s->tipo_cliente)->ascii()->lower()->toString(), ['', 'todos', $tipo], true));

        return $porClave->concat($relleno)->take($max)->values();
    }
}
