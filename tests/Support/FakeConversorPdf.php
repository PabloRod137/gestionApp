<?php

namespace Tests\Support;

use App\Support\Pdf\ConversorPdf;
use App\Support\Pdf\PdfException;

/** Conversor simulado: no usa LibreOffice. Devuelve un PDF mínimo válido y recuerda lo que recibió. */
class FakeConversorPdf implements ConversorPdf
{
    public static bool $fallar = false;
    /** @var array<int, string> */
    public static array $recibidos = [];

    public static function reset(): void
    {
        self::$fallar = false;
        self::$recibidos = [];
    }

    public function nombre(): string
    {
        return 'simulado';
    }

    public function disponible(): bool
    {
        return true;
    }

    public function convertir(string $docx, string $nombre = 'documento'): string
    {
        self::$recibidos[] = $docx;
        if (self::$fallar) {
            throw new PdfException('Conversor simulado: error de prueba.');
        }

        return "%PDF-1.4\n% generado desde un Word de ".strlen($docx)." bytes\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }
}
