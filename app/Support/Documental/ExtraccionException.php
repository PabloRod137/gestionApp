<?php

namespace App\Support\Documental;

use RuntimeException;

/** El documento no se puede leer (formato, tamaño, OCR ausente…). El mensaje es el motivo que se muestra a la persona. */
class ExtraccionException extends RuntimeException
{
}
