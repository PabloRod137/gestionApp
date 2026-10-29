<?php

namespace App\Support\Pdf;

use Symfony\Component\Process\Process;

/**
 * Convierte con Microsoft Word instalado en el equipo (solo Windows), manejándolo en segundo plano por automatización COM.
 * Es el motor preferido: el PDF sale exactamente como lo vería la persona al exportar desde Word.
 *
 * Importante: Word debe poder abrirse con la sesión del usuario que ejecuta el programa (uso en el propio ordenador).
 * Microsoft no soporta la automatización de Office desde servicios o servidores web; allí se usa LibreOffice.
 */
class WordConversor implements ConversorPdf
{
    private static ?bool $instalado = null;

    public function nombre(): string
    {
        return 'word';
    }

    public function disponible(): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        return self::$instalado ??= (new Process(['reg', 'query', 'HKCR\\Word.Application\\CLSID', '/ve']))->setTimeout(10)->run() === 0;
    }

    /**
     * La orden que se ejecuta. Las rutas viajan en variables de entorno y no dentro del texto del script,
     * así un nombre de archivo raro no puede colar comandos.
     *
     * @return array{comando: array<int, string>, script: string, env: array<string, string>}
     */
    public function orden(string $entrada, string $salida): array
    {
        $script = <<<'PS'
$ErrorActionPreference = 'Stop'
$word = $null
$doc = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $doc = $word.Documents.Open($env:LG_ENTRADA, $false, $true)
    $doc.ExportAsFixedFormat($env:LG_SALIDA, 17)
}
finally {
    if ($doc) { $doc.Close(0) }
    if ($word) { $word.Quit() }
}
PS;

        return [
            'comando' => ['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $script],
            'script' => $script,
            'env' => ['LG_ENTRADA' => $entrada, 'LG_SALIDA' => $salida],
        ];
    }

    public function convertir(string $docx, string $nombre = 'documento'): string
    {
        if (! $this->disponible()) {
            throw new PdfException('Microsoft Word no está instalado en este equipo.');
        }
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lg_word_'.bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        try {
            $entrada = $dir.DIRECTORY_SEPARATOR.'documento.docx';
            $salida = $dir.DIRECTORY_SEPARATOR.'documento.pdf';
            file_put_contents($entrada, $docx);

            $orden = $this->orden($entrada, $salida);
            $proceso = new Process($orden['comando'], $dir, $orden['env']);
            $proceso->setTimeout((float) config('documentos.pdf_timeout', 120));
            try {
                $proceso->run();
            } catch (\Throwable $e) {
                throw new PdfException('Word no terminó la conversión a tiempo: '.$e->getMessage());
            }
            $pdf = is_file($salida) ? (string) file_get_contents($salida) : '';
            if (! ConversorPdfAutomatico::pareceUnPdf($pdf)) {
                throw new PdfException('Word no pudo convertir el documento a PDF: '.trim(mb_substr($proceso->getErrorOutput().' '.$proceso->getOutput(), 0, 300)));
            }

            return $pdf;
        } finally {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }
}
