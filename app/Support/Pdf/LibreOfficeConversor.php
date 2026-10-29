<?php

namespace App\Support\Pdf;

use Symfony\Component\Process\ExecutableFinder;

/**
 * Convierte con LibreOffice en modo sin pantalla (soffice --headless). Gratuito, sin conexión y válido en Windows y Linux.
 * Cada conversión usa su propio perfil temporal, así dos conversiones a la vez no se bloquean entre sí.
 */
class LibreOfficeConversor implements ConversorPdf
{
    private const RUTAS_WINDOWS = [
        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
    ];

    public function nombre(): string
    {
        return 'libreoffice';
    }

    public function disponible(): bool
    {
        return $this->binario() !== null;
    }

    /** Si LIBREOFFICE_PATH está definido se usa solo esa ruta; si no, se busca en las rutas habituales y en el PATH. */
    private function binario(): ?string
    {
        $configurado = (string) config('documentos.libreoffice');
        if ($configurado !== '') {
            return is_file($configurado) ? $configurado : null;
        }
        foreach (self::RUTAS_WINDOWS as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }
        $finder = new ExecutableFinder;

        return $finder->find('soffice') ?? $finder->find('libreoffice');
    }

    public function convertir(string $docx, string $nombre = 'documento'): string
    {
        $bin = $this->binario();
        if (! $bin) {
            throw new PdfException('LibreOffice no está instalado o no se encuentra: instálalo o indica su ruta en LIBREOFFICE_PATH (ver docs/ARRANCAR_EN_LOCAL.md).');
        }
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lg_pdf_'.bin2hex(random_bytes(6));
        if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new PdfException('No se pudo crear una carpeta temporal para convertir el documento.');
        }
        try {
            $entrada = $dir.DIRECTORY_SEPARATOR.'documento.docx';
            file_put_contents($entrada, $docx);
            $perfil = 'file:///'.ltrim(str_replace('\\', '/', $dir.'/perfil'), '/');

            [$codigo, $salidaProceso] = $this->ejecutar([
                $bin, '--headless', '--norestore', '--nolockcheck', '--nodefault', '--nofirststartwizard',
                '-env:UserInstallation='.$perfil, '--convert-to', 'pdf:writer_pdf_Export', '--outdir', $dir, $entrada,
            ], $dir, (float) config('documentos.pdf_timeout', 120));

            $salida = $dir.DIRECTORY_SEPARATOR.'documento.pdf';
            $pdf = is_file($salida) ? (string) file_get_contents($salida) : '';
            if (! self::esPdf($pdf)) {
                throw new PdfException('LibreOffice no pudo convertir el documento a PDF (código '.$codigo.'): '.trim(mb_substr($salidaProceso, 0, 300)));
            }

            return $pdf;
        } finally {
            $this->borrar($dir);
        }
    }

    public static function esPdf(string $bytes): bool
    {
        return strlen($bytes) > 100 && str_starts_with($bytes, '%PDF-') && str_contains(substr($bytes, -1024), '%%EOF');
    }

    /**
     * Lanza LibreOffice con un entorno MÍNIMO (no hereda el de la aplicación). Dos motivos:
     *  - las claves y datos del .env no tienen por qué llegar a otro programa;
     *  - en Windows, con el servidor web de PHP, heredar las variables del .env hacía fallar la escritura del PDF
     *    (código 1) aunque desde la línea de comandos funcionara: así se comprobó en una instalación real.
     *
     * @param  array<int, string>  $comando
     * @return array{0: int, 1: string} código de salida y lo que escribió el proceso
     */
    private function ejecutar(array $comando, string $dir, float $limite): array
    {
        $proceso = proc_open($comando, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos, $dir, $this->entornoMinimo($dir), ['bypass_shell' => true]);
        if (! is_resource($proceso)) {
            throw new PdfException('No se pudo arrancar LibreOffice.');
        }
        fclose($tubos[0]);
        stream_set_blocking($tubos[1], false);
        stream_set_blocking($tubos[2], false);
        $salida = '';
        $inicio = microtime(true);
        $codigo = -1;
        while (true) {
            $salida .= (string) stream_get_contents($tubos[1]).(string) stream_get_contents($tubos[2]);
            $estado = proc_get_status($proceso);
            if (! $estado['running']) {
                $codigo = (int) $estado['exitcode'];
                break;
            }
            if (microtime(true) - $inicio > $limite) {
                proc_terminate($proceso);
                proc_close($proceso);
                throw new PdfException('LibreOffice no terminó la conversión a tiempo.');
            }
            usleep(50000);
        }
        $salida .= (string) stream_get_contents($tubos[1]).(string) stream_get_contents($tubos[2]);
        fclose($tubos[1]);
        fclose($tubos[2]);
        proc_close($proceso);

        return [$codigo, $salida];
    }

    /** @return array<string, string> */
    private function entornoMinimo(string $dir): array
    {
        $necesarias = ['PATH', 'SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA', 'PROGRAMDATA', 'PROGRAMFILES', 'PROGRAMFILES(X86)', 'COMSPEC', 'PATHEXT', 'COMPUTERNAME', 'USERNAME', 'OS', 'LANG', 'LC_ALL', 'DISPLAY', 'XDG_RUNTIME_DIR'];
        $entorno = ['HOME' => $dir];
        foreach (getenv() as $nombre => $valor) {
            if (in_array(strtoupper((string) $nombre), $necesarias, true)) {
                $entorno[$nombre] = (string) $valor;
            }
        }

        return $entorno;
    }

    private function borrar(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
