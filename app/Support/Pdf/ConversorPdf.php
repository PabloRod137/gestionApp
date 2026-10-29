<?php

namespace App\Support\Pdf;

/** Convierte un documento Word (.docx) en PDF. La conversión es local: el documento no sale del equipo del despacho. */
interface ConversorPdf
{
    /** Nombre corto del motor (word, libreoffice...). */
    public function nombre(): string;

    /** ¿Está el motor instalado y utilizable en este equipo? */
    public function disponible(): bool;

    /**
     * @param  string  $docx  contenido del archivo .docx
     * @return string contenido del PDF
     *
     * @throws PdfException si no se puede convertir
     */
    public function convertir(string $docx, string $nombre = 'documento'): string;
}
