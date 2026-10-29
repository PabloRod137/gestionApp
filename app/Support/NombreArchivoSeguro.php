<?php

namespace App\Support;

/**
 * Nombre de archivo apto para usarlo dentro de una ruta del servidor. Todo nombre que llega de fuera (un adjunto de correo,
 * un webhook, un flujo de Power Automate) es texto de otra persona: puede traer "../" o barras para escribir fuera de la carpeta prevista.
 */
class NombreArchivoSeguro
{
    private const LARGO_MAXIMO = 120;

    public static function limpiar(?string $nombre, string $porDefecto = 'documento.bin'): string
    {
        $n = str_replace('\\', '/', (string) $nombre);
        $n = basename($n);                                              // fuera cualquier ruta
        $n = preg_replace('/[\x00-\x1F\x7F]/u', '', $n) ?? '';          // sin caracteres de control
        $n = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $n) ?? '';      // solo letras, números, punto, guion, espacio
        $n = preg_replace('/\.{2,}/', '.', $n) ?? '';                   // sin ".."
        $n = trim($n, " ._");                                           // sin puntos ni guiones bajos al principio o al final
        if ($n === '') {
            return $porDefecto;
        }
        if (mb_strlen($n) > self::LARGO_MAXIMO) {
            $extension = pathinfo($n, PATHINFO_EXTENSION);
            $extension = mb_strlen($extension) <= 10 ? '.'.$extension : '';
            $n = mb_substr(pathinfo($n, PATHINFO_FILENAME), 0, self::LARGO_MAXIMO - mb_strlen($extension)).$extension;
        }

        return $n;
    }
}
