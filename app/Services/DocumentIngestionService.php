<?php

namespace App\Services;

use App\Models\User;
use App\Models\Client;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Events\DocumentIngestedEvent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DocumentIngestionService
{
    /**
     * Ingesta y archiva un documento en el sistema de forma automática.
     *
     * @param string $filePath Ruta local temporal del archivo
     * @param string $fileName Nombre original del archivo (ej: 'factura.xlsx')
     * @param string $channel Canal de entrada ('email', 'whatsapp', 'escaner')
     * @param array $meta Datos adicionales para identificación ('email', 'phone', 'client_id', 'expedient_id')
     * @return ExpedientDocument|null
     */
    public function ingest(string $filePath, string $fileName, string $channel, array $meta = []): ?ExpedientDocument
    {
        try {
            $client = $this->resolveClient($meta);

            if (!$client) {
                Log::channel($this->getLogChannel())->warning("DocumentIngestion: No se pudo emparejar el documento '{$fileName}' con ningún cliente registrado. Canal: {$channel}.", $meta);
                
                // Si entra por escáner y no se identifica, lo movemos a una zona de revisión del escáner
                if ($channel === 'escaner') {
                    $this->storeUnmatchedScan($filePath, $fileName);
                }
                return null;
            }

            // Resolver expediente (si no se proporciona, buscamos el último expediente abierto de este cliente)
            $expedientId = $meta['expedient_id'] ?? null;
            if (!$expedientId) {
                $latestExpedient = Expedient::where('client_id', $client->id)
                    ->whereIn('estado', ['abierto', 'en_proceso', 'pendiente_cliente'])
                    ->orderBy('created_at', 'desc')
                    ->first();
                $expedientId = $latestExpedient?->id;
            }

            // Generar la ruta de almacenamiento privada: private/clientes/{nif}/documentos_ingresados/{año}/{mes}/
            $cleanNif = preg_replace('/[^A-Za-z0-9]/', '', $client->nif);
            $year = date('Y');
            $month = date('m');
            $storageFolder = "private/clientes/{$cleanNif}/documentos_ingresados/{$year}/{$month}";
            
            // Generar un nombre único para evitar sobreescritura de archivos
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $baseName);
            $uniqueFileName = $safeBaseName . '_' . time() . '.' . $fileExtension;
            $finalPath = "{$storageFolder}/{$uniqueFileName}";

            // Almacenar el archivo físicamente en el disco privado
            // Almacenar el archivo físicamente en el disco privado usando Streams
            $stream = fopen($filePath, 'r');
            Storage::disk('local')->writeStream($finalPath, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            // Obtener el tipo MIME del archivo
            $mimeType = Storage::disk('local')->mimeType($finalPath);

            // Registrar el documento en la base de datos
            $document = ExpedientDocument::create([
                'expedient_id' => $expedientId,
                'client_id' => $client->id,
                'nombre' => $fileName,
                'ruta' => $finalPath,
                'tipo_mime' => $mimeType,
                'subido_por' => $client->user_id, // Asignado al usuario del cliente
                'visibilidad' => 'interno', // Por defecto interno hasta que el asesor lo apruebe o publique
                'canal_entrada' => $channel,
            ]);

            app(DocumentClassificationService::class)->classify($document);

            Log::channel($this->getLogChannel())->info("DocumentIngestion: Archivo '{$fileName}' archivado con éxito en '{$finalPath}'. Cliente: {$client->razon_social}. Canal: {$channel}.");

            // Lanzar el evento para disparar las notificaciones automáticas inmediatas
            event(new DocumentIngestedEvent($document));

            return $document;

        } catch (\Exception $e) {
            Log::channel($this->getLogChannel())->error("DocumentIngestion Error: " . $e->getMessage(), [
                'fileName' => $fileName,
                'channel' => $channel,
                'meta' => $meta,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Resuelve y localiza al cliente a partir de los metadatos proporcionados.
     */
    private function resolveClient(array $meta): ?Client
    {
        // 1. Si viene el client_id directamente
        if (!empty($meta['client_id'])) {
            return Client::find($meta['client_id']);
        }

        // 2. Si viene por email, buscar el usuario y su relación
        if (!empty($meta['email'])) {
            $user = User::where('email', trim($meta['email']))->first();
            if ($user && $user->client) {
                return $user->client;
            }
        }

        // 3. Si viene por teléfono (WhatsApp), buscamos por los últimos 9 dígitos
        if (!empty($meta['phone'])) {
            $cleanPhone = preg_replace('/\D/', '', $meta['phone']);
            if (strlen($cleanPhone) >= 9) {
                $last9 = substr($cleanPhone, -9);
                return Client::where('telefono', 'like', '%' . $last9)->first();
            }
        }

        return null;
    }

    /**
     * Almacena temporalmente los escaneos físicos que no han podido ser emparejados.
     */
    private function storeUnmatchedScan(string $filePath, string $fileName): void
    {
        try {
            $storageFolder = "scans/no_asignados";
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $baseName);
            $uniqueFileName = $safeBaseName . '_' . time() . '.' . $fileExtension;
            $finalPath = "{$storageFolder}/{$uniqueFileName}";

            $fileContents = file_get_contents($filePath);
            Storage::disk('local')->put($finalPath, $fileContents);
            Log::channel($this->getLogChannel())->warning("DocumentIngestion: Escaneo sin emparejar guardado en '{$finalPath}' para revisión manual.");
        } catch (\Exception $e) {
            Log::channel($this->getLogChannel())->error("DocumentIngestion: Error al guardar escaneo sin emparejar: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el canal de log adecuado según el entorno de la aplicación.
     */
    private function getLogChannel(): string
    {
        return config('app.env') === 'local' ? 'single' : 'stack';
    }
}
