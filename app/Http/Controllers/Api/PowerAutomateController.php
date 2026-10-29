<?php
// app/Http/Controllers/Api/PowerAutomateController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPowerAutomateDocumentJob;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Controlador del webhook de Power Automate.
 *
 * Responsabilidades (y SOLO estas):
 *  1. Validar el payload de la petición entrante.
 *  2. Descargar (URL) o decodificar (Base64) el archivo recibido.
 *  3. Guardar el archivo en disco temporalmente en storage/app/temp.
 *  4. Despachar el Job de procesamiento asíncrono a la cola 'documents'.
 *  5. Devolver 202 Accepted inmediatamente (no bloquear Power Automate).
 *
 * El procesamiento pesado (Claude, emparejamiento de expediente, MediaLibrary)
 * ocurre en ProcessPowerAutomateDocumentJob, completamente fuera de esta request.
 */
class PowerAutomateController extends Controller
{
    /**
     * POST /api/v1/power-automate/ingest
     *
     * Power Automate debe enviar un JSON con alguno de estos campos obligatorios:
     *   - file_url:     URL del archivo en OneDrive (SAS token incluido en la URL)
     *   - file_content: Contenido del archivo codificado en Base64
     *
     * Campos opcionales para el emparejamiento automático con expedientes:
     *   - filename:     Nombre original del archivo (con extensión)
     *   - client_nif:   NIF/CIF del cliente para buscar su expediente
     *   - client_email: Email del cliente (alternativa al NIF)
     *   - expedient_id: ID directo del expediente (si PA ya lo conoce)
     *   - callback_url: URL a la que Nortia notificará el resultado final
     */
    public function ingest(Request $request)
    {
        // ── Validación del payload ────────────────────────────────────────────
        $validator = Validator::make($request->all(), [
            // Al menos una fuente de archivo es obligatoria
            'file_url'     => 'required_without:file_content|nullable|url',
            'file_content' => 'required_without:file_url|nullable|string',

            // Metadatos opcionales
            'filename'     => 'nullable|string|max:255',
            'client_nif'   => 'nullable|string|max:20',
            'client_email' => 'nullable|email',
            'expedient_id' => 'nullable|integer|exists:expedients,id',
            'callback_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            Log::warning('PowerAutomate: Payload inválido recibido.', [
                'errors' => $validator->errors()->toArray(),
                'ip'     => $request->ip(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Payload inválido. Revisa los campos requeridos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // ── Descarga / decodificación del archivo ─────────────────────────────
        // Guardamos el archivo en disco ANTES de despachar el job para que el
        // job no necesite credenciales ni acceso a la URL de OneDrive, que puede
        // tener un SAS token temporal que expire antes de que el worker procese.
        try {
            [$tempPath, $filename, $mimeType] = $this->resolveFile($request);
        } catch (\Exception $e) {
            Log::error('PowerAutomate: Error al obtener el archivo.', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'No se pudo obtener el archivo: ' . $e->getMessage(),
            ], 502);
        }

        // ── Emparejamiento optimista con cliente ──────────────────────────────
        // Intentamos localizar el cliente ahora para pasarlo al job, pero si no
        // se encuentra el job volverá a intentarlo con mayor capacidad de búsqueda.
        $clientId    = $this->resolveClientId($request);
        $expedientId = $request->integer('expedient_id') ?: null;

        // ── Generar ID único para rastrear el job ─────────────────────────────
        // Este UUID permite a Power Automate consultar /job-status/{jobId} para
        // hacer polling del resultado si no usa el mecanismo de callback.
        $jobId = (string) Str::uuid();

        // Guardar estado inicial en caché (24h de TTL)
        Cache::put("pa_job_{$jobId}", [
            'status'     => 'queued',
            'filename'   => $filename,
            'queued_at'  => now()->toIso8601String(),
        ], now()->addHours(24));

        // ── Despachar el job a la cola 'documents' ────────────────────────────
        // IMPORTANTE: dispatch() es no bloqueante. El job se encola y el
        // controlador retorna 202 de inmediato. Claude procesa en background.
        ProcessPowerAutomateDocumentJob::dispatch(
            jobId:       $jobId,
            tempPath:    $tempPath,
            filename:    $filename,
            mimeType:    $mimeType,
            clientId:    $clientId,
            expedientId: $expedientId,
            callbackUrl: $request->input('callback_url'),
            metadata:    $request->except(['file_content', 'file_url']),
        )->onQueue('documents');

        Log::info("PowerAutomate: Documento '{$filename}' encolado.", [
            'job_id'       => $jobId,
            'client_id'    => $clientId,
            'expedient_id' => $expedientId,
            'has_callback' => !empty($request->input('callback_url')),
        ]);

        // ── 202 Accepted ──────────────────────────────────────────────────────
        // Power Automate recibe esta respuesta en < 1 segundo y puede continuar
        // su flujo sin bloquear. El job_id le permite hacer polling del estado.
        return response()->json([
            'status'     => 'accepted',
            'message'    => 'Documento recibido y encolado para clasificación con IA.',
            'job_id'     => $jobId,
            'status_url' => route('api.powerautomate.job-status', $jobId),
        ], 202);
    }

    /**
     * GET /api/v1/power-automate/job-status/{jobId}
     *
     * Devuelve el estado actual de un job de procesamiento.
     * Power Automate puede hacer polling periódico a este endpoint.
     *
     * Posibles valores de 'status' en la respuesta:
     *   - queued:     El job está esperando en la cola
     *   - processing: El job está siendo ejecutado por el worker
     *   - completed:  Procesamiento finalizado con éxito
     *   - failed:     Todos los reintentos se agotaron sin éxito
     */
    public function jobStatus(string $jobId)
    {
        $data = Cache::get("pa_job_{$jobId}");

        if (!$data) {
            return response()->json([
                'error' => 'Job no encontrado o expirado (TTL de 24h superado).',
            ], 404);
        }

        return response()->json([
            'job_id' => $jobId,
            ...$data,
        ]);
    }

    // ── Métodos de ayuda privados ─────────────────────────────────────────────

    /**
     * Resuelve el archivo desde URL o desde Base64 y lo guarda en disco temporal.
     *
     * @return array [$tempPath, $filename, $mimeType]
     * @throws \RuntimeException si el archivo no puede obtenerse
     */
    private function resolveFile(Request $request): array
    {
        $filename = $request->input('filename', 'documento_onedrive_' . time());

        if ($request->filled('file_url')) {
            // Descargar directamente desde la URL de OneDrive
            // El SAS token ya va incluido en la URL que Power Automate nos envía
            $response = Http::timeout(30)->get($request->input('file_url'));

            if (!$response->successful()) {
                throw new \RuntimeException(
                    "HTTP {$response->status()} al descargar desde OneDrive. URL expirada o sin permisos."
                );
            }

            // Deducir tipo MIME y extensión de las cabeceras de respuesta
            $mimeType = explode(';', $response->header('Content-Type', 'application/octet-stream'))[0];

            // Si el nombre no tiene extensión, deducirla del Content-Type
            if (!str_contains($filename, '.')) {
                $filename .= '.' . $this->guessExtension($mimeType);
            }

            $fileContent = $response->body();

        } else {
            // Decodificar contenido Base64 (alternativa para PA cuando no puede compartir URL)
            $fileContent = base64_decode($request->input('file_content'), true);

            if ($fileContent === false) {
                throw new \RuntimeException('El contenido Base64 proporcionado no es válido.');
            }

            $mimeType = $this->guessMimeType($filename);
        }

        // Guardar en directorio temporal dedicado dentro de storage/app
        // NUNCA usar /tmp del sistema: no es accesible entre contenedores Docker
        $tempDir = storage_path('app/temp/power_automate');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Usar UUID para evitar colisiones entre jobs concurrentes
        $tempPath = $tempDir . '/' . Str::uuid() . '_' . \App\Support\NombreArchivoSeguro::limpiar($filename);
        file_put_contents($tempPath, $fileContent);

        return [$tempPath, $filename, $mimeType];
    }

    /**
     * Busca el client_id a partir del NIF o email que Power Automate envía.
     * Si no se puede emparejar, devuelve null y el job lo intentará de nuevo.
     */
    private function resolveClientId(Request $request): ?int
    {
        // Si viene el expedient_id, el job puede encontrar el client_id directamente
        if ($request->filled('expedient_id')) {
            return null;
        }

        // Búsqueda por NIF (campo más fiable para clientes de gestoría)
        if ($request->filled('client_nif')) {
            $client = Client::where('nif', $request->input('client_nif'))->first();
            if ($client) return $client->id;
        }

        // Búsqueda por email del usuario asociado al cliente
        if ($request->filled('client_email')) {
            $client = Client::whereHas(
                'user',
                fn($q) => $q->where('email', $request->input('client_email'))
            )->first();
            if ($client) return $client->id;
        }

        return null;
    }

    /**
     * Deduce la extensión de archivo más probable a partir del tipo MIME.
     */
    private function guessExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf'    => 'pdf',
            'image/jpeg'         => 'jpg',
            'image/png'          => 'png',
            'image/tiff'         => 'tiff',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
            'application/msword' => 'doc',
            default              => 'bin',
        };
    }

    /**
     * Deduce el tipo MIME a partir de la extensión del nombre de archivo.
     */
    private function guessMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf'        => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'        => 'image/png',
            'tiff', 'tif' => 'image/tiff',
            'docx'       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx'       => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc'        => 'application/msword',
            default      => 'application/octet-stream',
        };
    }
}
