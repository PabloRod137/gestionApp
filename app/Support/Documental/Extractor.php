<?php

namespace App\Support\Documental;

use Smalot\PdfParser\Parser;
use ZipArchive;

/**
 * Lee el texto de un documento en este equipo, sin enviarlo a ningún sitio: txt, csv, docx, xlsx, PDF con texto y,
 * si hay OCR instalado, imágenes y PDF escaneados. Devuelve el texto por páginas y el método usado.
 */
class Extractor
{
    public function __construct(private readonly Ocr $ocr)
    {
    }

    /**
     * @return array{paginas: array<int, string>, metodo: string}
     *
     * @throws ExtraccionException
     */
    public function extraer(string $ruta, string $extension): array
    {
        $extension = strtolower($extension);
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new ExtraccionException('No se puede leer el archivo.');
        }
        if (filesize($ruta) > (int) config('documental.max_bytes')) {
            throw new ExtraccionException('Archivo demasiado grande para procesarlo.');
        }

        [$paginas, $metodo] = match ($extension) {
            'txt', 'csv' => [[$this->plano($ruta)], 'texto'],
            'docx' => [[$this->docx($ruta)], 'office'],
            'xlsx' => [[$this->xlsx($ruta)], 'office'],
            'pdf' => $this->pdf($ruta),
            'png', 'jpg', 'jpeg' => [[$this->ocr->imagen($ruta)], 'ocr'],
            default => throw new ExtraccionException('Formato no admitido.'),
        };

        return ['paginas' => $this->limitar($paginas), 'metodo' => $metodo];
    }

    private function plano(string $ruta): string
    {
        $t = (string) file_get_contents($ruta);

        return mb_check_encoding($t, 'UTF-8') ? $t : mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
    }

    private function docx(string $ruta): string
    {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new ExtraccionException('El Word está dañado o protegido.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new ExtraccionException('El Word no tiene contenido legible.');
        }
        $xml = str_replace(['</w:p>', '<w:br/>', '<w:tab/>'], ["\n", "\n", "\t"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function xlsx(string $ruta): string
    {
        try {
            $lector = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($ruta);
            $lector->setReadDataOnly(true);
            $libro = $lector->load($ruta);
        } catch (\Throwable) {
            throw new ExtraccionException('El Excel está dañado o protegido.');
        }
        $limite = (int) config('documental.max_caracteres');
        $texto = '';
        foreach ($libro->getWorksheetIterator() as $hoja) {
            $texto .= '['.$hoja->getTitle()."]\n";
            foreach ($hoja->toArray(null, true, false, false) as $fila) {
                $texto .= implode("\t", array_map(fn ($c) => (string) $c, $fila))."\n";
                if (strlen($texto) > $limite) {
                    break 2;
                }
            }
        }

        return $texto;
    }

    /** @return array{0: array<int, string>, 1: string} */
    private function pdf(string $ruta): array
    {
        $paginas = [];
        try {
            foreach ((new Parser())->parseFile($ruta)->getPages() as $pagina) {
                $paginas[] = (string) $pagina->getText();
            }
        } catch (\Throwable) {
            throw new ExtraccionException('El PDF está dañado, protegido o no se puede leer.');
        }
        $sinTexto = mb_strlen(preg_replace('/\s+/u', '', implode('', $paginas))) < 20;
        if ($sinTexto) {
            return [$this->ocr->pdf($ruta), 'ocr'];
        }

        return [$paginas, 'pdf'];
    }

    /** Recorta al máximo de caracteres, página a página. @param array<int, string> $paginas @return array<int, string> */
    private function limitar(array $paginas): array
    {
        $resto = (int) config('documental.max_caracteres');
        $r = [];
        foreach ($paginas as $p) {
            $p = trim(preg_replace("/[ \t]+/", ' ', (string) $p));
            if ($resto <= 0) {
                break;
            }
            $r[] = mb_substr($p, 0, $resto);
            $resto -= mb_strlen($p);
        }

        return $r;
    }
}
