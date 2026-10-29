<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocumentIngestionService;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PollScannedFiles extends Command
{
    /**
     * El nombre y firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'scans:poll';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Procesa automáticamente los archivos depositados por los escáneres de red en la oficina';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(DocumentIngestionService $ingestionService): int
    {
        $appEnv = config('app.env');
        $logChannel = ($appEnv === 'local') ? 'single' : 'stack';

        $this->info("Iniciando escaneo de directorio de archivos escaneados... [Entorno: {$appEnv}]");
        Log::channel($logChannel)->info("ScansPoll: Buscando archivos del escáner en storage/app/scans.");

        $scanDir = storage_path('app/scans');

        // Asegurarse de que el directorio del escáner de red existe
        if (!file_exists($scanDir)) {
            mkdir($scanDir, 0777, true);
            $this->info("Creado directorio para escaneos: " . $scanDir);
            return Command::SUCCESS;
        }

        $files = glob($scanDir . '/*');
        $processedCount = 0;

        foreach ($files as $filePath) {
            // Ignorar carpetas y el archivo de sistema
            if (is_dir($filePath) || basename($filePath) === '.gitignore') {
                continue;
            }

            $fileName = basename($filePath);
            $this->info("Detectado archivo escaneado: {$fileName}");

            $client = null;
            $meta = [];

            // 1. Intentar buscar un patrón de NIF/CIF español (ej: A39123456 o 12345678A) en el nombre
            // Coincide con 8 números y una letra final, o una letra inicial, 7 números y letra/número final
            if (preg_match('/([A-Z0-9][0-9]{7}[A-Z0-9])/i', $fileName, $matches)) {
                $nif = strtoupper($matches[1]);
                $client = Client::where('nif', $nif)->first();
                if ($client) {
                    $this->info("Emparejado con cliente NIF [{$nif}] -> {$client->razon_social}");
                    $meta['client_id'] = $client->id;
                }
            }

            // 2. Si no se encontró por NIF, probar por patrón id_cliente (ej: id_1_factura.pdf o id1-nomina.pdf)
            if (!$client && preg_match('/id_?([0-9]+)/i', $fileName, $matches)) {
                $clientId = $matches[1];
                $client = Client::find($clientId);
                if ($client) {
                    $this->info("Emparejado con cliente por ID [{$clientId}] -> {$client->razon_social}");
                    $meta['client_id'] = $client->id;
                }
            }

            // Ingestar el documento (si no hay cliente emparejado, se moverá a "no_asignados" internamente)
            $document = $ingestionService->ingest(
                $filePath,
                $fileName,
                'escaner',
                $meta
            );

            if ($document) {
                $this->info("Archivo escaneado '{$fileName}' archivado automáticamente.");
                $processedCount++;
            } else {
                $this->warn("Archivo escaneado '{$fileName}' guardado en revisión manual (no se identificó cliente).");
            }

            // Eliminar el archivo de origen del directorio del escáner en todos los casos
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $this->info("Procesamiento de escáner finalizado. Procesados: {$processedCount} archivos.");
        return Command::SUCCESS;
    }
}
