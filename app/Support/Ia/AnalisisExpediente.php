<?php

namespace App\Support\Ia;

use App\Models\Expedient;
use App\Support\PropuestaDocumental;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Análisis bajo demanda con un modelo local (Ollama), limitado al cliente del expediente activo.
 * Primero se consulta lo que ya sabe el programa; el modelo solo interviene después y su respuesta
 * siempre queda como "pendiente de validación": nunca escribe, envía ni busca en otros clientes.
 */
class AnalisisExpediente
{
    private const CLAVES = ['dato_propuesta', 'fuente', 'documento', 'ruta', 'expediente_origen', 'justificacion', 'estado_validacion'];

    private const SISTEMA = 'Eres un analista de expediente. Responde solo JSON con las claves dato_propuesta, fuente, documento, ruta, expediente_origen, '
        .'justificacion, estado_validacion. No inventes datos. Si no hay evidencia, dato_propuesta debe ser "NO LOCALIZADO". Distingue datos estables de '
        .'circunstanciales. No propongas escrituras ni envíos. Usa exclusivamente el JSON recibido.';

    /** @return array{resultado: array<string,string>, ruta: string, modelo: string, archivos_considerados: int, otros_clientes_consultados: bool} */
    public function analizar(Expedient $e, string $pregunta): array
    {
        if (! config('ia.enabled')) {
            throw new IaException('La IA local está desactivada.');
        }
        $pregunta = trim($pregunta);
        if ($pregunta === '' || mb_strlen($pregunta) > 1200) {
            throw new IaException('Indica una duda concreta (máximo 1.200 caracteres).');
        }
        $e->loadMissing('client', 'service');

        // Lo que ya se sabe sin modelo: sin servicio no se pueden determinar requisitos ni documentos
        if (! $e->service_id) {
            return $this->respuesta([
                'dato_propuesta' => 'Completar el servicio del expediente: sin él no se pueden determinar requisitos ni documentos.',
                'fuente' => 'Datos del expediente', 'documento' => '', 'ruta' => '', 'expediente_origen' => (string) $e->codigo,
                'justificacion' => 'El expediente no tiene servicio asignado.',
            ], 'LOCAL', 'NINGUNO', 0);
        }

        $archivos = $this->inventario($e);
        $c = $e->client;
        $propuesta = PropuestaDocumental::para($e);
        $payload = [
            'expediente' => [
                'expediente_id' => $e->codigo, 'cliente' => $c->razon_social, 'tipo_cliente' => $c->tipo, 'nif' => $c->nif, 'email' => $c->email,
                'telefono' => $c->telefono, 'direccion' => $c->direccion, 'servicio' => $e->service?->nombre, 'titulo' => $e->titulo, 'estado' => $e->estado,
            ],
            'otros_expedientes_mismo_cliente' => Expedient::where('client_id', $e->client_id)->where('id', '!=', $e->id)->with('service')->latest('id')->take(10)->get()
                ->map(fn ($o) => ['expediente_id' => $o->codigo, 'titulo' => $o->titulo, 'estado' => $o->estado, 'servicio' => $o->service?->nombre])->all(),
            'propuesta_documental' => [
                'requisitos' => $propuesta['aportar']->take(12)->pluck('descripcion')->all(),
                'documento_que_prepara_el_despacho' => $propuesta['generar']->pluck('nombre')->all(),
            ],
            'archivos_mismo_cliente' => $archivos,
            'pregunta' => $pregunta,
        ];

        $modelo = (string) config('ia.ollama.model');
        try {
            $r = Http::timeout((int) config('ia.ollama.timeout'))->post(rtrim((string) config('ia.ollama.url'), '/').'/api/chat', [
                'model' => $modelo, 'stream' => false, 'think' => false, 'format' => 'json',
                'options' => ['temperature' => 0, 'num_ctx' => 4096, 'num_predict' => 180],
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
            throw new IaException('El modelo local no devolvió una respuesta utilizable. Prueba con una pregunta más concreta.');
        }

        return $this->respuesta($json, 'LOCAL_OLLAMA', $modelo, count($archivos));
    }

    private function respuesta(array $datos, string $ruta, string $modelo, int $archivos): array
    {
        $seguro = [];
        foreach (self::CLAVES as $k) {
            $seguro[$k] = mb_substr((string) ($datos[$k] ?? ''), 0, 1000);
        }
        // aunque el modelo diga otra cosa, una persona debe confirmarlo
        $seguro['estado_validacion'] = 'PENDIENTE_VALIDACION';

        return ['resultado' => $seguro, 'ruta' => $ruta, 'modelo' => $modelo, 'archivos_considerados' => $archivos, 'otros_clientes_consultados' => false];
    }

    /** Nombres de los archivos de la carpeta de ESE cliente (nunca de otro), sin leer su contenido. @return array<int, array{documento: string, ruta: string}> */
    private function inventario(Expedient $e, int $limite = 15): array
    {
        $raiz = config('ia.carpetas_raiz');
        $ruta = (string) $e->client->ruta_sharepoint;
        if (! $raiz || ! is_dir($raiz) || $ruta === '') {
            return [];
        }
        $partes = array_values(array_filter(explode('/', str_replace('\\', '/', $ruta)), fn ($p) => ! in_array($p, ['', '.'], true)));
        if (in_array('..', $partes, true)) {
            return [];
        }
        if ($partes && mb_strtolower($partes[0]) === 'documentos compartidos') {
            array_shift($partes);
        }
        $i = null;
        foreach ($partes as $k => $p) {
            if (mb_strtolower($p) === '01_clientes') {
                $i = $k;
                break;
            }
        }
        if ($i === null || ! isset($partes[$i + 1])) {
            return [];
        }
        $carpeta = realpath($raiz.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, array_slice($partes, 0, $i + 2)));
        $rootReal = realpath($raiz);
        if (! $carpeta || ! $rootReal || ! str_starts_with($carpeta, $rootReal) || ! is_dir($carpeta)) {
            return [];
        }
        $permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'msg'];
        $filas = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && in_array(strtolower($f->getExtension()), $permitidas, true)) {
                $filas[] = ['documento' => $f->getFilename(), 'ruta' => ltrim(str_replace($rootReal, '', $f->getPathname()), '\\/')];
                if (count($filas) >= $limite) {
                    break;
                }
            }
        }

        return $filas;
    }
}
