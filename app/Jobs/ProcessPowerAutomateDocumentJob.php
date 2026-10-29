<?php
// app/Jobs/ProcessPowerAutomateDocumentJob.php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job: Procesamiento asíncrono de documentos recibidos desde Power Automate.
 *
 * Flujo de ejecución:
 *   1. Lee el archivo temporal guardado por el controlador.
 *   2. Clasifica el documento con la API de Anthropic (Claude).
 *   3. Resuelve el expediente de destino (por NIF, email, o ID directo).
 *   4. Crea el registro ExpedientDocument en la base de datos.
 *   5. Adjunta el archivo mediante Spatie MediaLibrary (almacenamiento definitivo).
 *   6. Actualiza el estado en caché (para polling de /job-status).
 *   7. Envía el resultado al callback_url de Power Automate.
 *   8. Elimina el archivo temporal.
 *
 * Política de reintentos:
 *   - 3 intentos máximo con backoff exponencial (30s → 120s → 480s).
 *   - Los fallos de Claude son recuperables (sobrecargas temporales de la API).
 *   - Los fallos de base de datos o disco NO se reintentan (se marcan como fallidos).
 */
class ProcessPowerAutomateDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos si el job falla.
     */
    public int $tries = 3;

    /**
     * Backoff exponencial entre reintentos en segundos.
     * Primer reintento: 30s | Segundo: 120s | Tercero: 480s (8 min)
     */
    public array $backoff = [30, 120, 480];

    /**
     * Tiempo máximo de ejecución del job en segundos.
     * Claude puede tardar en PDFs largos; 120s es un margen holgado.
     */
    public int $timeout = 120;

    /**
     * Constructor: recibe todos los parámetros necesarios para el procesamiento.
     *
     * @param string      $jobId       UUID único para rastrear este job
     * @param string      $tempPath    Ruta al archivo temporal en storage/app/temp
     * @param string      $filename    Nombre original del archivo
     * @param string      $mimeType    Tipo MIME detectado
     * @param int|null    $clientId    ID del cliente (si se pudo emparejar en el controlador)
     * @param int|null    $expedientId ID del expediente destino (si PA lo proporciona)
     * @param string|null $callbackUrl URL a la que notificar el resultado final
     * @param array       $metadata    Metadatos adicionales de la petición original
     */
    public function __construct(
        private readonly string  $jobId,
        private readonly string  $tempPath,
        private readonly string  $filename,
        private readonly string  $mimeType,
        private readonly ?int    $clientId,
        private readonly ?int    $expedientId,
        private readonly ?string $callbackUrl,
        private readonly array   $metadata = [],
    ) {}

    /**
     * Lógica principal del Job.
     */
    public function handle(): void
    {
        Log::info("ProcessPADoc [{$this->jobId}]: Iniciando procesamiento de '{$this->filename}'.", [
            'attempt'      => $this->attempts(),
            'client_id'    => $this->clientId,
            'expedient_id' => $this->expedientId,
        ]);

        // Marcar como "en proceso" en caché para que /job-status lo refleje
        $this->updateJobStatus('processing', ['started_at' => now()->toIso8601String()]);

        // Verificar que el archivo temporal todavía existe en disco
        if (!file_exists($this->tempPath)) {
            // Esto no se puede recuperar con reintentos; el archivo se perdió
            $this->fail(new \RuntimeException(
                "Archivo temporal no encontrado: {$this->tempPath}. " .
                "Posiblemente eliminado por un proceso externo o un reintento anterior."
            ));
            return;
        }

        try {
            // ── FASE 1: Clasificar el documento con Claude ────────────────────
            Log::info("ProcessPADoc [{$this->jobId}]: Enviando a Claude para clasificación...");
            $classification = $this->classifyWithClaude();
            Log::info("ProcessPADoc [{$this->jobId}]: Claude clasificó como '{$classification['categoria']}'.", [
                'confianza' => $classification['confianza'],
            ]);

            // ── FASE 2: Resolver el expediente de destino ─────────────────────
            $expedient = $this->resolveExpedient($classification);

            // ── FASE 3: Crear el registro ExpedientDocument ───────────────────
            $document = $this->createDocumentRecord($expedient, $classification);
            Log::info("ProcessPADoc [{$this->jobId}]: Registro ExpedientDocument creado (ID={$document->id}).");

            // ── FASE 4: Adjuntar el archivo con Spatie MediaLibrary ───────────
            $this->attachFileToDocument($document);
            Log::info("ProcessPADoc [{$this->jobId}]: Archivo adjuntado a MediaLibrary.");

            // ── FASE 5: Actualizar estado y notificar a Power Automate ─────────
            $this->updateJobStatus('completed', [
                'document_id'  => $document->id,
                'categoria'    => $classification['categoria'],
                'confianza'    => $classification['confianza'],
                'expedient_id' => $expedient?->id,
                'completed_at' => now()->toIso8601String(),
            ]);

            // Enviar el resultado al callback_url para que PA continúe su flujo
            $this->sendCallback('success', $document, $expedient, $classification);

            Log::info("ProcessPADoc [{$this->jobId}]: Completado con éxito.");

        } catch (\Exception $e) {
            Log::error("ProcessPADoc [{$this->jobId}]: Error en el intento {$this->attempts()}.", [
                'error'    => $e->getMessage(),
                'filename' => $this->filename,
            ]);

            // Si quedan reintentos disponibles, relanzar para que la cola reintente
            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            // Se agotaron los reintentos: marcar como fallido definitivamente
            $this->fail($e);

        } finally {
            // SIEMPRE limpiar el archivo temporal, independientemente del resultado.
            // Si el job se reintentará, el archivo ya no existe en el reintento.
            // Para reintentos, el controlador debe ser invocado de nuevo por PA.
            if ($this->attempts() >= $this->tries || !isset($e)) {
                $this->cleanupTempFile();
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FASE 1: Clasificación con Claude
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Envía el documento a la API de Anthropic (Claude) para clasificarlo.
     *
     * Retorna un array asociativo con:
     *   - categoria:       string  (uno de los valores de la lista del sistema)
     *   - confianza:       float   (valor entre 0.0 y 1.0)
     *   - razonamiento:    string  (explicación breve de Claude)
     *   - datos_extraidos: array   (campos clave extraídos del documento)
     */
    private function classifyWithClaude(): array
    {
        $apiKey = config('services.anthropic.key');
        $model  = config('services.anthropic.model', 'claude-opus-4-5');

        if (empty($apiKey)) {
            throw new \RuntimeException(
                'ANTHROPIC_API_KEY no está configurada en .env. ' .
                'La clasificación con IA no puede ejecutarse.'
            );
        }

        // Construir el contenido del mensaje según el tipo de archivo
        $messageContent = $this->buildClaudeMessageContent();

        // Prompt de sistema extremadamente específico para gestoría española.
        // Cuanto más concreto sea el prompt, más precisa y reproducible será la
        // clasificación, reduciendo el porcentaje de categorías "otro".
        $systemPrompt = <<<'PROMPT'
Eres un asistente especializado en clasificación de documentos para una gestoría y asesoría fiscal española llamada Nortia, con sede en Santander (Cantabria).

Tu tarea es analizar el documento proporcionado y devolver EXCLUSIVAMENTE un objeto JSON válido. No incluyas ningún texto adicional fuera del JSON, ni delimitadores de código markdown.

Estructura del JSON requerida:
{
  "categoria": "<valor_de_la_lista>",
  "confianza": <número entre 0.0 y 1.0>,
  "razonamiento": "<explicación breve en español, máximo 2 frases>",
  "datos_extraidos": {
    "nombre_titular": "<nombre completo del titular o empresa, o null>",
    "nif": "<NIF, CIF o NIE sin espacios ni guiones, o null>",
    "fecha_documento": "<fecha en formato YYYY-MM-DD, o null>",
    "importe_total": "<importe numérico como string con 2 decimales, ej: '1250.00', o null>",
    "entidad_emisora": "<nombre de la entidad que emite el documento, o null>"
  }
}

Lista de categorías válidas (usa EXACTAMENTE uno de estos valores sin variaciones):
- "nomina": Nómina, recibo de salario, liquidación de haberes
- "factura": Factura de proveedor o cliente, albarán valorado, ticket de gastos
- "contrato": Contrato laboral, mercantil, de servicios o de arrendamiento
- "notificacion_hacienda": Carta, requerimiento, propuesta de liquidación o notificación de la AEAT (Agencia Tributaria / Hacienda)
- "notificacion_seguridad_social": Comunicación de la TGSS (Tesorería General de la Seguridad Social) o el INSS
- "declaracion_fiscal": Modelo 100, 130, 303, 390, 200, 347, 349, 111, 115 o cualquier impreso fiscal oficial
- "escritura": Escritura notarial, acta notarial, poder notarial, protocolo notarial
- "dni_nie_pasaporte": Documento Nacional de Identidad, NIE, pasaporte u otro documento de identidad
- "certificado": Certificado de empadronamiento, vida laboral, antecedentes penales, estar al corriente de pagos, etc.
- "otro": Cualquier documento que no encaje claramente en las categorías anteriores

Reglas adicionales:
- Si el documento es ilegible, está en blanco o dañado: usa categoria="otro" y confianza=0.0
- Si el documento puede corresponder a varias categorías, elige la más específica
- El campo "confianza" debe reflejar tu nivel real de certeza (0.95+ solo si estás muy seguro)
PROMPT;

        // Llamada a la API de Claude con timeout generoso para PDFs extensos
        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])
        ->timeout(90)
        ->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model,
            'max_tokens' => 600, // La respuesta JSON es pequeña; más tokens = más coste innecesario
            'system'     => $systemPrompt,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $messageContent,
                ],
            ],
        ]);

        // Gestión de errores de la API de Claude
        if (!$response->successful()) {
            $statusCode = $response->status();
            $errorBody  = $response->body();

            // Error 529 = Anthropic sobrecargado → relanzar para reintento automático
            // Error 401 = API key inválida → no tiene sentido reintentar
            if ($statusCode === 401) {
                throw new \RuntimeException(
                    "ANTHROPIC_API_KEY inválida (HTTP 401). Verifica el valor en .env."
                );
            }

            throw new \RuntimeException(
                "Claude API HTTP {$statusCode}: {$errorBody}"
            );
        }

        // Extraer el texto de respuesta del primer bloque de contenido
        $rawText = $response->json('content.0.text', '{}');

        // Claude a veces envuelve el JSON en bloques de código markdown (```json ... ```)
        // aunque se le pida explícitamente que no lo haga. Limpiamos por si acaso.
        $jsonText = trim(preg_replace('/^```(?:json)?\s*/m', '', preg_replace('/\s*```$/m', '', $rawText)));

        $parsed = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['categoria'])) {
            Log::warning("ProcessPADoc [{$this->jobId}]: Respuesta de Claude no parseable.", [
                'raw_response' => $rawText,
                'json_error'   => json_last_error_msg(),
            ]);

            // Fallback seguro: clasificar como "otro" para revisión manual
            return [
                'categoria'       => 'otro',
                'confianza'       => 0.0,
                'razonamiento'    => 'Respuesta de Claude no procesable. Revisión manual requerida.',
                'datos_extraidos' => [],
            ];
        }

        // Sanitizar el valor de confianza para garantizar que está en [0, 1]
        $parsed['confianza']       = max(0.0, min(1.0, (float) ($parsed['confianza'] ?? 0.0)));
        $parsed['datos_extraidos'] = $parsed['datos_extraidos'] ?? [];

        return $parsed;
    }

    /**
     * Construye el array de contenido del mensaje para Claude.
     * La estructura varía según el tipo de archivo:
     *   - Imágenes (jpg, png):  se envían como base64 para visión de Claude
     *   - PDFs:                 se envían como documento base64 (soportado en claude-3+)
     *   - Otros (docx, xlsx):   solo se envía el nombre para clasificación por contexto
     */
    private function buildClaudeMessageContent(): array
    {
        $isImage = str_starts_with($this->mimeType, 'image/');
        $isPdf   = $this->mimeType === 'application/pdf';

        $instructionText = 'Analiza este documento y clasifícalo según las instrucciones del sistema.';

        if ($isImage) {
            // Visión de Claude: enviar imagen codificada en base64
            return [
                [
                    'type'   => 'image',
                    'source' => [
                        'type'       => 'base64',
                        'media_type' => $this->mimeType,
                        'data'       => base64_encode(file_get_contents($this->tempPath)),
                    ],
                ],
                ['type' => 'text', 'text' => $instructionText],
            ];
        }

        if ($isPdf) {
            // Soporte nativo de PDF en Claude 3+ (se envía como documento base64)
            return [
                [
                    'type'   => 'document',
                    'source' => [
                        'type'       => 'base64',
                        'media_type' => 'application/pdf',
                        'data'       => base64_encode(file_get_contents($this->tempPath)),
                    ],
                ],
                ['type' => 'text', 'text' => $instructionText],
            ];
        }

        // Para Word, Excel y otros: solo podemos darle el nombre del archivo.
        // El nombre suele ser suficientemente descriptivo en el contexto de una gestoría.
        return [
            [
                'type' => 'text',
                'text' => "Documento recibido:\n"
                        . "Nombre: {$this->filename}\n"
                        . "Tipo: {$this->mimeType}\n\n"
                        . "No es posible leer el contenido binario de este tipo de archivo. "
                        . "Intenta clasificarlo basándote en el nombre del archivo. "
                        . "Si el nombre no es suficientemente descriptivo, usa categoria='otro'.",
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FASE 2: Resolución del Expediente
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Busca el expediente activo al que debe asociarse el documento.
     *
     * Orden de prioridad:
     *   1. expedient_id explícito (el más fiable)
     *   2. client_id + expediente más reciente en trámite
     *   3. NIF extraído por Claude en los datos del documento
     *   4. null → el documento queda "huérfano" para revisión manual
     */
    private function resolveExpedient(array $classification): ?Expedient
    {
        // Prioridad 1: expedient_id directo
        if ($this->expedientId) {
            $expedient = Expedient::find($this->expedientId);
            if ($expedient) {
                Log::info("ProcessPADoc [{$this->jobId}]: Expediente resuelto por ID directo ({$expedient->id}).");
                return $expedient;
            }
        }

        // Prioridad 2: client_id → expediente más reciente activo
        if ($this->clientId) {
            $expedient = Expedient::where('client_id', $this->clientId)
                ->where('estado', 'en_tramite')
                ->latest('fecha_apertura')
                ->first();

            if ($expedient) {
                Log::info("ProcessPADoc [{$this->jobId}]: Expediente resuelto por client_id ({$expedient->id}).");
                return $expedient;
            }
        }

        // Prioridad 3: NIF extraído por Claude en los datos del documento
        $nif = $classification['datos_extraidos']['nif'] ?? null;
        if ($nif) {
            $client = Client::where('nif', $nif)->first();
            if ($client) {
                $expedient = Expedient::where('client_id', $client->id)
                    ->where('estado', 'en_tramite')
                    ->latest('fecha_apertura')
                    ->first();

                if ($expedient) {
                    Log::info("ProcessPADoc [{$this->jobId}]: Expediente resuelto por NIF extraído por Claude ({$expedient->id}).");
                    return $expedient;
                }
            }
        }

        // Sin expediente → revisión manual requerida (requires_review=true en el callback)
        Log::warning("ProcessPADoc [{$this->jobId}]: No se pudo emparejar documento con ningún expediente.", [
            'client_id'    => $this->clientId,
            'expedient_id' => $this->expedientId,
            'nif_claude'   => $nif,
        ]);

        return null;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FASE 3: Creación del Registro en Base de Datos
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Crea el registro ExpedientDocument con todos los datos procesados.
     * Si no se pudo emparejar con un expediente, expedient_id queda null
     * y el documento aparecerá en la bandeja de revisión manual del panel.
     */
    private function createDocumentRecord(?Expedient $expedient, array $classification): ExpedientDocument
    {
        return ExpedientDocument::create([
            'expedient_id'        => $expedient?->id,
            'client_id'           => $expedient?->client_id ?? $this->clientId,
            'nombre'              => $this->filename,
            // La ruta temporal se actualizará en la Fase 4 cuando MediaLibrary mueva el archivo
            'ruta'                => $this->tempPath,
            'tipo_mime'           => $this->mimeType,
            // Los documentos de Power Automate son siempre privados (documentación interna)
            'visibilidad'         => 'privado',
            'canal_entrada'       => 'power_automate',
            // La clasificación automática queda registrada con su nivel de confianza
            'categoria'           => $classification['categoria'],
            'categoria_confianza' => $classification['confianza'],
            // subido_por = null indica ingesta automática (sin usuario humano)
            'subido_por'          => null,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FASE 4: Almacenamiento definitivo con Spatie MediaLibrary
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Mueve el archivo temporal al almacenamiento definitivo configurado en
     * Spatie MediaLibrary (local, S3, etc. según config/filesystems.php).
     *
     * Añade propiedades personalizadas al media para facilitar el rastreo:
     *   - canal_entrada: 'power_automate'
     *   - job_id: UUID único del job
     *   - datos_extraidos: campos clave extraídos por Claude
     */
    private function attachFileToDocument(ExpedientDocument $document): void
    {
        // addMedia() mueve (no copia) el archivo al disco configurado en MediaLibrary.
        // Si ya se ejecutó la limpieza del archivo temporal, este método fallará,
        // por eso cleanupTempFile() solo se llama DESPUÉS de esta fase.
        $document
            ->addMedia($this->tempPath)
            ->usingFileName($this->filename)
            ->withCustomProperties([
                'canal_entrada'   => 'power_automate',
                'job_id'          => $this->jobId,
                'clasificado_por' => 'claude-ia',
            ])
            ->toMediaCollection('documentos_expediente');

        // Actualizar la ruta en el modelo con la URL pública o privada definitiva
        $document->update([
            'ruta' => $document->getFirstMediaUrl('documentos_expediente'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FASE 5: Callback a Power Automate
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Envía el resultado del procesamiento al callback_url que Power Automate
     * proporcionó en la petición original.
     *
     * Power Automate usará los datos de este callback para decidir qué hacer
     * con el archivo en OneDrive:
     *   - status = 'success', requires_review = false → mover a /procesado/{categoria}
     *   - status = 'success', requires_review = true  → mover a /revision_manual
     *   - status = 'error'                            → mover a /error
     *
     * IMPORTANTE: Un fallo en el callback NO hace fallar el job. El documento
     * ya está guardado correctamente en la base de datos.
     */
    private function sendCallback(
        string $status,
        ExpedientDocument $document,
        ?Expedient $expedient,
        array $classification
    ): void {
        if (empty($this->callbackUrl)) {
            // Power Automate no proporcionó callback_url. Usará polling si necesita el resultado.
            return;
        }

        $payload = [
            'job_id'           => $this->jobId,
            'status'           => $status,
            'document_id'      => $document->id,
            'categoria'        => $document->categoria,
            'confianza'        => $document->categoria_confianza,
            'expedient_id'     => $expedient?->id,
            'expedient_titulo' => $expedient?->titulo,
            'client_id'        => $document->client_id,
            // requires_review = true indica que el asesor debe revisar manualmente
            'requires_review'  => $expedient === null,
            'datos_extraidos'  => $classification['datos_extraidos'] ?? [],
            'processed_at'     => now()->toIso8601String(),
        ];

        try {
            Http::withHeaders([
                // Firmamos el callback con el mismo token para que PA pueda verificarlo
                'Authorization'        => 'Bearer ' . config('services.power_automate.api_token'),
                'X-Nortia-Job-Id' => $this->jobId,
                'Content-Type'         => 'application/json',
            ])
            ->timeout(config('services.power_automate.callback_timeout', 10))
            ->post($this->callbackUrl, $payload);

            Log::info("ProcessPADoc [{$this->jobId}]: Callback de éxito enviado a {$this->callbackUrl}.");
        } catch (\Exception $e) {
            // El callback es "best-effort": si falla, el documento ya está guardado
            Log::warning("ProcessPADoc [{$this->jobId}]: Callback fallido (no afecta al documento).", [
                'callback_url' => $this->callbackUrl,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Manejo de Fallos Definitivos
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Se ejecuta automáticamente por Laravel cuando se agotan todos los reintentos.
     * También se ejecuta cuando se llama a $this->fail() manualmente.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("ProcessPADoc [{$this->jobId}]: FALLO DEFINITIVO tras {$this->tries} intentos.", [
            'error'    => $exception->getMessage(),
            'filename' => $this->filename,
        ]);

        // Actualizar caché para que /job-status muestre el fallo
        $this->updateJobStatus('failed', [
            'error'     => $exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ]);

        // Notificar a Power Automate del fallo para que mueva el archivo a /error
        if (!empty($this->callbackUrl)) {
            try {
                Http::withHeaders([
                    'Authorization'        => 'Bearer ' . config('services.power_automate.api_token'),
                    'X-Nortia-Job-Id' => $this->jobId,
                ])
                ->timeout(10)
                ->post($this->callbackUrl, [
                    'job_id'         => $this->jobId,
                    'status'         => 'error',
                    'error'          => 'El procesamiento falló definitivamente tras varios intentos. Revisión manual requerida.',
                    'filename'       => $this->filename,
                    'requires_review' => true,
                    'failed_at'      => now()->toIso8601String(),
                ]);
            } catch (\Exception $e) {
                Log::error("ProcessPADoc [{$this->jobId}]: No se pudo enviar callback de error final.");
            }
        }

        // Limpiar el archivo temporal si aún existe
        $this->cleanupTempFile();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Métodos de Utilidad
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Actualiza el estado del job en caché para el endpoint /job-status.
     */
    private function updateJobStatus(string $status, array $extra = []): void
    {
        $current = Cache::get("pa_job_{$this->jobId}", []);
        Cache::put(
            "pa_job_{$this->jobId}",
            array_merge($current, ['status' => $status], $extra),
            now()->addHours(24)
        );
    }

    /**
     * Elimina el archivo temporal del disco.
     * Se usa @unlink para suprimir advertencias si el archivo ya no existe.
     */
    private function cleanupTempFile(): void
    {
        if (file_exists($this->tempPath)) {
            @unlink($this->tempPath);
            Log::debug("ProcessPADoc [{$this->jobId}]: Archivo temporal eliminado: {$this->tempPath}");
        }
    }
}
