<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocumentIngestionService;
use Webklex\IMAP\Facades\Client as ImapClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PollIncomingEmails extends Command
{
    /**
     * El nombre y firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'mail:poll';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Consulta el correo IMAP para procesar documentos de clientes automáticos';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(DocumentIngestionService $ingestionService): int
    {
        $appEnv = config('app.env');
        $logChannel = ($appEnv === 'local') ? 'single' : 'stack';

        $this->info("Iniciando escaneo de correos electrónicos... [Entorno: {$appEnv}]");
        Log::channel($logChannel)->info("MailPoll: Iniciando escaneo automático de buzón IMAP.");

        $host = config('imap.accounts.default.host');

        // Si no está configurado el servidor IMAP real, corremos el modo MOCK
        if (empty($host) || $host === '127.0.0.1' || $host === 'localhost') {
            $this->warn("IMAP_HOST no configurado o local. Ejecutando en Modo Simulación (Mock)...");
            return $this->handleMockPoll($ingestionService);
        }

        try {
            // Conectar al cliente IMAP predeterminado
            $client = ImapClient::account('default');
            $client->connect();

            // Obtener carpetas de correo (bandeja de entrada)
            $folders = $client->getFolders();

            foreach ($folders as $folder) {
                // Solo procesar bandeja de entrada para optimizar
                if (strtolower($folder->name) !== 'inbox' && strtolower($folder->name) !== 'incoming') {
                    continue;
                }

                // Obtener correos no leídos (unseen)
                $messages = $folder->query()->unseen()->get();

                $this->info("Procesando " . count($messages) . " correos nuevos.");

                foreach ($messages as $message) {
                    $senderEmail = $message->getFrom()[0]->mail ?? null;

                    if (!$senderEmail) {
                        $this->warn("Mensaje sin remitente de correo válido. Saltando.");
                        continue;
                    }

                    $attachments = $message->getAttachments();
                    $this->info("Email de [{$senderEmail}] contiene " . count($attachments) . " adjunto(s).");

                    foreach ($attachments as $attachment) {
                        // Crear ruta temporal para el adjunto
                        $tempDir = storage_path('app/temp');
                        if (!file_exists($tempDir)) {
                            mkdir($tempDir, 0777, true);
                        }

                        $tempPath = $tempDir . '/' . time() . '_' . \App\Support\NombreArchivoSeguro::limpiar($attachment->getName());
                        
                        // Guardar contenido en la ruta temporal
                        file_put_contents($tempPath, $attachment->getContent());

                        // Enviar al servicio de ingesta
                        $document = $ingestionService->ingest(
                            $tempPath,
                            $attachment->getName(),
                            'email',
                            ['email' => $senderEmail]
                        );

                        if ($document) {
                            $this->info("Adjunto '{$attachment->getName()}' procesado con éxito.");
                        } else {
                            $this->error("No se pudo procesar el adjunto '{$attachment->getName()}'.");
                        }

                        // Limpiar archivo temporal
                        if (file_exists($tempPath)) {
                            @unlink($tempPath);
                        }
                    }

                    // Marcar correo como leído
                    $message->setFlag('Seen');
                }
            }

            $this->info("Escaneo IMAP finalizado con éxito.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error en conexión IMAP: " . $e->getMessage());
            Log::channel($logChannel)->error("MailPoll IMAP Connection Error: " . $e->getMessage());
            
            $this->info("Realizando fallback a modo de simulación...");
            return $this->handleMockPoll($ingestionService);
        }
    }

    /**
     * Procesa archivos en el directorio local de simulación (Mock) para facilitar pruebas sin servidor IMAP real.
     */
    private function handleMockPoll(DocumentIngestionService $ingestionService): int
    {
        $mockDir = storage_path('app/mock_emails');
        if (!file_exists($mockDir)) {
            mkdir($mockDir, 0777, true);
            // Crear un archivo de instrucciones de ejemplo
            file_put_contents($mockDir . '/README.txt', "Deja archivos aquí renombrados como: email_cliente_com_nombre_archivo.xlsx para simular la llegada de un adjunto.");
        }

        $files = glob($mockDir . '/*');
        $processedCount = 0;

        foreach ($files as $filePath) {
            if (is_dir($filePath) || basename($filePath) === 'README.txt') {
                continue;
            }

            $fileName = basename($filePath);
            
            // Patrón esperado: remitente_correo.com_nombrearchivo.ext
            // Reemplazamos la primera ocurrencia del guión bajo después de la extensión de correo
            // O un formato más sencillo: email#archivo.ext (usando un separador claro)
            // Ej: cliente@cliente.es_balance_2026.xlsx
            if (preg_match('/^([a-zA-Z0-9\.\-\_\+]+@[a-zA-Z0-9\.\-]+)_(.+)$/', $fileName, $matches)) {
                $senderEmail = $matches[1];
                $originalName = $matches[2];

                $this->info("Simulando correo de [{$senderEmail}] con archivo [{$originalName}]");

                $document = $ingestionService->ingest(
                    $filePath,
                    $originalName,
                    'email',
                    ['email' => $senderEmail]
                );

                if ($document) {
                    $this->info("Simulación: Archivo procesado con éxito.");
                    @unlink($filePath); // Borrar el archivo simulado para que no se repita
                    $processedCount++;
                } else {
                    $this->error("Simulación: No se pudo procesar el archivo.");
                }
            } else {
                $this->warn("Simulación: El archivo '{$fileName}' no coincide con el patrón esperado 'email@dominio.com_nombre.ext'. Saltando.");
            }
        }

        $this->info("Modo Simulación finalizado. Procesados: {$processedCount} archivos.");
        return Command::SUCCESS;
    }
}
