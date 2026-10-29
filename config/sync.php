<?php

// Puente con SharePoint (a través de Power Automate). El programa NO escribe en SharePoint: deja cada avance como un evento,
// lo entrega (archivo en una carpeta o API) y espera a que SharePoint lo refleje.
return [
    // Carpeta donde se dejan los archivos para Power Automate (normalmente una carpeta de SharePoint/OneDrive sincronizada).
    // Vacío = storage/app/sincronizacion/salida.
    'carpeta_salida' => env('SYNC_EXPORT_DIR', ''),

    // Exportación programada cada cinco minutos (apagada por defecto hasta que Power Automate esté preparado).
    'activo' => (bool) env('SYNC_EXPORT_ENABLED', false),

    // Un expediente "No iniciado" pasa a "En curso" con su primer avance (propuesta aprobada o documento generado).
    // Nunca se finaliza solo: cerrar un expediente es decisión de una persona.
    'avance_automatico' => (bool) env('SYNC_AVANCE_AUTOMATICO', true),

    // Horas tras las que un evento entregado y no confirmado se marca como "sin confirmar".
    'aviso_horas' => (int) env('SYNC_AVISO_HORAS', 2),
];
