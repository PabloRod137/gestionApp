<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentIngestionService;
use App\Support\NombreArchivoSeguro;
use App\Support\UrlSegura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Entrada de documentos por WhatsApp. APAGADA por defecto (WHATSAPP_WEBHOOK_ENABLED): es una integración heredada que el
 * despacho no usa. Si se enciende:
 *  - cada petición se autentica (firma de Meta con WHATSAPP_APP_SECRET o clave compartida WHATSAPP_WEBHOOK_SECRET); sin
 *    ninguna de las dos configuradas, se rechaza todo;
 *  - la dirección del adjunto debe ser https, de un servidor de la lista (WHATSAPP_MEDIA_HOSTS) y no apuntar a redes internas;
 *  - la clave de la API solo viaja a esos servidores, no se siguen redirecciones y hay un tamaño máximo;
 *  - el nombre del archivo se limpia: nunca decide dónde se guarda.
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(protected DocumentIngestionService $ingestionService) {}

    /** Verificación inicial de Meta (GET). */
    public function verify(Request $request)
    {
        abort_unless(config('services.whatsapp.enabled'), 404);

        $esperado = (string) config('services.whatsapp.verify_token');
        $recibido = (string) ($request->query('hub_verify_token') ?? $request->query('verify_token') ?? '');
        $modo = $request->query('hub_mode') ?? $request->query('mode');
        if ($esperado !== '' && $modo === 'subscribe' && hash_equals($esperado, $recibido)) {
            return response((string) ($request->query('hub_challenge') ?? $request->query('challenge')), 200);
        }

        return response('Forbidden', 403);
    }

    /** Recibe un archivo por WhatsApp (POST). */
    public function handle(Request $request)
    {
        abort_unless(config('services.whatsapp.enabled'), 404);

        $falloAutenticacion = $this->autenticar($request);
        if ($falloAutenticacion) {
            return $falloAutenticacion;
        }
        // en el registro no entra el contenido de la petición (números de teléfono, textos): solo qué campos llegaron
        Log::info('WhatsAppWebhook: petición recibida.', ['campos' => array_keys($request->all())]);

        $validador = Validator::make($request->all(), [
            'phone' => 'required_without_all:from,sender,wa_id|string',
            'media_url' => 'required_without_all:url,file_url|string',
        ]);
        if ($validador->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Faltan parámetros obligatorios (número de teléfono o URL del archivo).', 'errors' => $validador->errors()], 400);
        }

        $telefono = $request->input('phone') ?? $request->input('from') ?? $request->input('sender') ?? $request->input('wa_id');
        $urlArchivo = (string) ($request->input('media_url') ?? $request->input('url') ?? $request->input('file_url'));
        $nombre = NombreArchivoSeguro::limpiar($request->input('filename') ?? $request->input('name') ?? $request->input('caption'), 'documento_whatsapp.bin');

        if (! UrlSegura::permitida($urlArchivo, (array) config('services.whatsapp.media_hosts'))) {
            Log::warning('WhatsAppWebhook: dirección de archivo rechazada (no es https, no está en la lista o apunta a una red interna).');

            return response()->json(['status' => 'error', 'message' => 'La dirección del archivo no está permitida.'], 422);
        }

        try {
            $cliente = Http::timeout(20)->withoutRedirecting();
            if ($clave = config('services.whatsapp.api_token')) {
                $cliente = $cliente->withToken($clave);   // solo llega a servidores de la lista: ya comprobado arriba
            }
            $respuesta = $cliente->get($urlArchivo);
            if (! $respuesta->successful()) {
                Log::error('WhatsAppWebhook: no se pudo descargar el archivo (código '.$respuesta->status().').');

                return response()->json(['status' => 'error', 'message' => 'No se pudo descargar el archivo adjunto desde la URL de WhatsApp.'], 502);
            }
            $maximo = (int) config('services.whatsapp.max_bytes');
            $cuerpo = $respuesta->body();
            if ((int) $respuesta->header('Content-Length') > $maximo || strlen($cuerpo) > $maximo) {
                return response()->json(['status' => 'error', 'message' => 'El archivo es demasiado grande.'], 413);
            }

            if ($nombre === 'documento_whatsapp.bin') {
                $extension = [
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'application/vnd.ms-excel' => 'xls',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/msword' => 'doc',
                    'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
                ][strtolower(trim(explode(';', (string) $respuesta->header('Content-Type'))[0]))] ?? 'bin';
                $nombre = 'documento_whatsapp_'.time().'.'.$extension;
            }

            $carpeta = storage_path('app/temp/whatsapp');
            if (! is_dir($carpeta) && ! mkdir($carpeta, 0750, true) && ! is_dir($carpeta)) {
                throw new \RuntimeException('No se pudo crear la carpeta temporal.');
            }
            $ruta = $carpeta.DIRECTORY_SEPARATOR.Str::uuid().'_'.$nombre;
            file_put_contents($ruta, $cuerpo);

            try {
                $documento = $this->ingestionService->ingest($ruta, $nombre, 'whatsapp', ['phone' => $telefono]);
            } finally {
                @unlink($ruta);
            }

            if ($documento) {
                return response()->json(['status' => 'success', 'message' => 'Documento de WhatsApp recibido, archivado y notificado con éxito.', 'document_id' => $documento->id], 200);
            }

            return response()->json(['status' => 'partial', 'message' => 'Documento recibido pero archivado en la bandeja de revisión manual por falta de emparejamiento con el cliente.'], 200);
        } catch (\Throwable $e) {
            Log::error('WhatsAppWebhook: error al procesar el archivo: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Error interno al procesar el webhook de WhatsApp.'], 500);
        }
    }

    /** null si la petición es de confianza; si no, la respuesta de rechazo. */
    private function autenticar(Request $request)
    {
        $secretoApp = (string) config('services.whatsapp.app_secret');
        $secretoCompartido = (string) config('services.whatsapp.webhook_secret');
        if ($secretoApp === '' && $secretoCompartido === '') {
            Log::critical('WhatsAppWebhook: encendido sin WHATSAPP_APP_SECRET ni WHATSAPP_WEBHOOK_SECRET; se rechaza todo.');

            return response()->json(['status' => 'error', 'message' => 'Servicio no configurado.'], 503);
        }
        if ($secretoApp !== '') {
            $firma = 'sha256='.hash_hmac('sha256', $request->getContent(), $secretoApp);
            if (hash_equals($firma, (string) $request->header('X-Hub-Signature-256'))) {
                return null;
            }
        }
        if ($secretoCompartido !== '') {
            $recibido = (string) ($request->bearerToken() ?? $request->header('X-Webhook-Secret'));
            if ($recibido !== '' && hash_equals($secretoCompartido, $recibido)) {
                return null;
            }
        }

        return response()->json(['status' => 'error', 'message' => 'No autorizado.'], 401);
    }
}
