<?php

namespace App\Support\Documental;

/**
 * OCR local con Tesseract (imágenes) y Poppler `pdftoppm` (convierte un PDF escaneado en imágenes). Todo en este equipo.
 * Los programas se lanzan sin shell, con el entorno mínimo y con tiempo máximo. Si no están instalados, `disponible()` es false
 * y el documento se anota como omitido (nunca se inventa texto).
 */
class Ocr
{
    private static ?bool $disponible = null;
    private static ?bool $poppler = null;

    public function disponible(): bool
    {
        return self::$disponible ??= $this->existe($this->tesseract(), ['--version']);
    }

    public function conPdf(): bool
    {
        return $this->disponible() && (self::$poppler ??= $this->existe($this->pdftoppm(), ['-v']));
    }

    /** Texto de una imagen. @throws ExtraccionException */
    public function imagen(string $ruta): string
    {
        if (! $this->disponible()) {
            throw new ExtraccionException('Imagen sin texto: falta instalar el OCR (Tesseract).');
        }
        [$codigo, $salida] = $this->ejecutar([$this->tesseract(), $ruta, 'stdout', '-l', (string) config('documental.ocr.idiomas', 'spa+eng')]);
        if ($codigo !== 0) {
            throw new ExtraccionException('El OCR no pudo leer la imagen.');
        }

        return $salida;
    }

    /** Texto de cada página de un PDF escaneado (las primeras `max_paginas_ocr`). @return array<int, string> @throws ExtraccionException */
    public function pdf(string $ruta): array
    {
        if (! $this->conPdf()) {
            throw new ExtraccionException('PDF escaneado: falta instalar el OCR (Tesseract y Poppler).');
        }
        $max = (int) config('documental.max_paginas_ocr', 10);
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr-'.bin2hex(random_bytes(6));
        mkdir($dir, 0700);
        try {
            [$codigo] = $this->ejecutar([$this->pdftoppm(), '-r', '150', '-f', '1', '-l', (string) $max, '-png', $ruta, $dir.DIRECTORY_SEPARATOR.'p']);
            if ($codigo !== 0) {
                throw new ExtraccionException('No se pudo convertir el PDF escaneado en imágenes.');
            }
            $paginas = [];
            $imagenes = glob($dir.DIRECTORY_SEPARATOR.'p*.png') ?: [];
            sort($imagenes);
            foreach ($imagenes as $img) {
                $paginas[] = $this->imagen($img);
            }

            return $paginas;
        } finally {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    private function tesseract(): string
    {
        return config('documental.ocr.tesseract') ?: 'tesseract';
    }

    private function pdftoppm(): string
    {
        return config('documental.ocr.pdftoppm') ?: 'pdftoppm';
    }

    private function existe(string $programa, array $args): bool
    {
        try {
            return $this->ejecutar([$programa, ...$args], 10)[0] === 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{0:int,1:string} código de salida y salida estándar */
    private function ejecutar(array $orden, ?int $limite = null): array
    {
        $entorno = array_filter([
            'PATH' => getenv('PATH') ?: '', 'SystemRoot' => getenv('SystemRoot') ?: null, 'TEMP' => sys_get_temp_dir(), 'TMP' => sys_get_temp_dir(),
            'TESSDATA_PREFIX' => getenv('TESSDATA_PREFIX') ?: null, 'HOME' => getenv('HOME') ?: null,
        ], fn ($v) => $v !== null);
        $p = @proc_open($orden, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, null, $entorno);
        if (! is_resource($p)) {
            return [127, ''];
        }
        stream_set_blocking($tuberias[1], false);
        stream_set_blocking($tuberias[2], false);
        $salida = '';
        $fin = time() + ($limite ?? (int) config('documental.ocr.timeout', 120));
        while (true) {
            $estado = proc_get_status($p);
            $salida .= stream_get_contents($tuberias[1]);
            stream_get_contents($tuberias[2]);
            if (! $estado['running']) {
                break;
            }
            if (time() > $fin) {
                proc_terminate($p);
                fclose($tuberias[1]);
                fclose($tuberias[2]);
                proc_close($p);
                throw new ExtraccionException('El OCR tardó demasiado.');
            }
            usleep(50000);
        }
        $salida .= stream_get_contents($tuberias[1]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);
        $codigo = (int) $estado['exitcode'];   // solo es fiable en la primera lectura tras terminar
        proc_close($p);

        return [$codigo, $salida];
    }
}
