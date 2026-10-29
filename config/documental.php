<?php

/*
| Conocimiento documental local: inventario de las carpetas de cada cliente, lectura del texto (con OCR si hace falta)
| y propuestas («hallazgos») que una persona acepta o rechaza una a una. Todo ocurre en este equipo: ningún documento
| sale a ningún servicio. Apagado por defecto. Ver docs/INDEXACION_DOCUMENTAL.md.
*/
return [
    'activo' => (bool) env('DOCUMENTAL_ACTIVO', false),

    // Carpeta raíz de los documentos del despacho (biblioteca sincronizada en este equipo). Se comparte con la IA local.
    'raiz' => env('DOCUMENTAL_RAIZ', env('IA_CARPETAS_RAIZ')),

    // Se procesan solo estos formatos. Los demás se anotan como omitidos con su motivo.
    'extensiones' => ['pdf', 'docx', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg'],
    'omitidas' => [
        'doc' => 'Word antiguo (.doc): no hay un conversor seguro acreditado',
        'xls' => 'Excel antiguo (.xls): no hay un conversor seguro acreditado',
        'msg' => 'Correo de Outlook (.msg): no se procesa',
    ],

    // Límites por archivo
    'max_bytes' => (int) env('DOCUMENTAL_MAX_BYTES', 15 * 1024 * 1024),
    'max_caracteres' => 200000,
    'max_paginas_ocr' => 10,

    // OCR local (Tesseract para imágenes y Poppler/pdftoppm para convertir PDF escaneados en imágenes). Vacío = buscarlos en el PATH.
    'ocr' => [
        'tesseract' => env('TESSERACT_PATH', ''),
        'pdftoppm' => env('PDFTOPPM_PATH', ''),
        'idiomas' => env('TESSERACT_IDIOMAS', 'spa+eng'),
        'timeout' => 120,
    ],

    // Cola local de procesado y tamaño de cada pasada del inventario
    'cola' => 'documentos',
    // Conexión de la cola: 'database' (hay que tener el trabajador en marcha, scripts/trabajador-documental.ps1) o 'sync' (se procesa en el momento, sin trabajador)
    'conexion' => env('DOCUMENTAL_COLA_CONEXION', 'database'),
    'lote' => (int) env('DOCUMENTAL_LOTE', 200),
    'reintentos' => 3,
];
