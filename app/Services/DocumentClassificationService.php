<?php

namespace App\Services;

use App\Models\ExpedientDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class DocumentClassificationService
{
    /**
     * Clasifica un documento usando la API de Anthropic Claude y actualiza el registro.
     *
     * @param ExpedientDocument $document
     * @return void
     */
    public function classify(ExpedientDocument $document): void
    {
        try {
            $apiKey = config('services.anthropic.key');
            if (empty($apiKey)) {
                Log::warning("DocumentClassification: ANTHROPIC_API_KEY no está configurada.");
                return;
            }

            $nombre = $document->nombre;
            $mime = $document->tipo_mime;
            $fragmento = '';

            // Si el archivo es un PDF, intentar extraer los primeros 1500 caracteres
            if ($mime === 'application/pdf' && !empty($document->ruta) && Storage::exists($document->ruta)) {
                try {
                    $parser = new Parser();
                    $pdfPath = Storage::path($document->ruta);
                    $pdf = $parser->parseFile($pdfPath);
                    $text = $pdf->getText();
                    $fragmento = mb_substr($text, 0, 1500);
                } catch (\Exception $pdfException) {
                    Log::warning("DocumentClassification: Error al extraer texto de PDF '{$nombre}': " . $pdfException->getMessage());
                }
            }

            // Realizar la llamada HTTP a Anthropic Claude API
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 150,
                    'system' => "Eres un clasificador de documentos para una gestoría española. Analiza el nombre del archivo, su tipo MIME y (si se proporciona) un fragmento del contenido, y devuelve ÚNICAMENTE un JSON con dos campos: 'categoria' y 'confianza'. \n\nValores válidos para 'categoria': nomina, factura, contrato, notificacion_hacienda, notificacion_seguridad_social, declaracion_fiscal, escritura, dni_nie, justificante_pago, correspondencia, otro.\nValores válidos para 'confianza': alta, media, baja.\n\nNo añadas explicaciones. Solo el JSON.",
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => "Nombre: {$nombre}\nMIME: {$mime}\nContenido: {$fragmento}"
                        ]
                    ]
                ]);

            if ($response->failed()) {
                Log::warning("DocumentClassification: La petición a la API falló con estado " . $response->status() . ": " . $response->body());
                return;
            }

            $result = $response->json();
            $textResponse = $result['content'][0]['text'] ?? '';

            // Limpiar posibles bloques de código de markdown (ej. ```json ... ```)
            $jsonString = trim($textResponse);
            if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $jsonString, $matches)) {
                $jsonString = trim($matches[1]);
            }

            $data = json_decode($jsonString, true);

            if (is_array($data) && isset($data['categoria']) && isset($data['confianza'])) {
                // Validar que la categoría y confianza pertenezcan a los valores permitidos
                $valoresValidosCategoria = [
                    'nomina', 'factura', 'contrato', 'notificacion_hacienda', 
                    'notificacion_seguridad_social', 'declaracion_fiscal', 
                    'escritura', 'dni_nie', 'justificante_pago', 'correspondencia', 'otro'
                ];
                $valoresValidosConfianza = ['alta', 'media', 'baja'];

                $categoria = in_array($data['categoria'], $valoresValidosCategoria) ? $data['categoria'] : 'otro';
                $confianza = in_array($data['confianza'], $valoresValidosConfianza) ? $data['confianza'] : 'baja';

                $document->update([
                    'categoria' => $categoria,
                    'categoria_confianza' => $confianza,
                ]);

                Log::info("DocumentClassification: Documento ID {$document->id} clasificado como '{$categoria}' con confianza '{$confianza}'.");
            } else {
                Log::warning("DocumentClassification: JSON de respuesta inválido o incompleto. Respuesta: " . $textResponse);
            }

        } catch (\Exception $e) {
            Log::warning("DocumentClassification: Error durante la clasificación del documento ID {$document->id}: " . $e->getMessage());
        }
    }
}
