<?php

// Conversión de los documentos Word generados a PDF (Microsoft Word o LibreOffice, siempre en local).
return [
    // Motor de PDF: auto = Microsoft Word si está instalado y, si no, LibreOffice; o fuerza uno: word | libreoffice.
    'motor' => env('PDF_MOTOR', 'auto'),
    // Ruta completa a soffice si no está en el PATH ni en la carpeta de instalación habitual. Vacío = buscarlo solo.
    'libreoffice' => env('LIBREOFFICE_PATH', ''),
    // Segundos máximos por conversión (la primera suele tardar más).
    'pdf_timeout' => (int) env('PDF_TIMEOUT', 120),
];
