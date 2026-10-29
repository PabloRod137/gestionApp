<?php

namespace App\Support\Pdf;

/**
 * Elige el motor de PDF: Microsoft Word si está instalado (mejor fidelidad) y LibreOffice como respaldo.
 * Si el primero falla al convertir se prueba con el siguiente; solo si ninguno puede, se avisa de qué instalar.
 */
class ConversorPdfAutomatico implements ConversorPdf
{
    /** @param array<int, ConversorPdf> $motores por orden de preferencia */
    public function __construct(private array $motores) {}

    public static function desdeConfiguracion(): self
    {
        return new self(match ((string) config('documentos.motor', 'auto')) {
            'word' => [new WordConversor],
            'libreoffice' => [new LibreOfficeConversor],
            default => [new WordConversor, new LibreOfficeConversor],
        });
    }

    /** @return array<int, string> */
    public function nombresDeMotores(): array
    {
        return array_map(fn (ConversorPdf $m) => $m->nombre(), $this->motores);
    }

    public function nombre(): string
    {
        return 'automático';
    }

    public function disponible(): bool
    {
        foreach ($this->motores as $m) {
            if ($m->disponible()) {
                return true;
            }
        }

        return false;
    }

    public function convertir(string $docx, string $nombre = 'documento'): string
    {
        $fallos = [];
        foreach ($this->motores as $motor) {
            if (! $motor->disponible()) {
                continue;
            }
            try {
                $pdf = $motor->convertir($docx, $nombre);
                if (self::pareceUnPdf($pdf)) {
                    return $pdf;
                }
                $fallos[] = $motor->nombre().': no devolvió un PDF válido';
            } catch (PdfException $e) {
                $fallos[] = $motor->nombre().': '.$e->getMessage();
            }
        }
        if (! $fallos) {
            throw new PdfException('No hay ningún programa instalado para crear el PDF. Instala Microsoft Word o LibreOffice (ver docs/ARRANCAR_EN_LOCAL.md).');
        }

        throw new PdfException('No se pudo crear el PDF. '.implode(' | ', $fallos));
    }

    public static function pareceUnPdf(string $bytes): bool
    {
        return str_starts_with($bytes, '%PDF-') && str_contains(substr($bytes, -1024), '%%EOF');
    }
}
