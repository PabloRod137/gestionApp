<?php

/*
| Veri*Factu: sistema informático de facturación (SIF) del despacho, modalidad «solo VERI*FACTU» (cada registro de
| facturación se remite a la AEAT en el momento). Ver docs/VERIFACTU.md.
| Nada de esto sale hacia la AEAT mientras VERIFACTU_ACTIVO sea false: los registros se generan y encadenan igual y
| quedan pendientes de envío.
*/
return [
    'activo' => (bool) env('VERIFACTU_ACTIVO', false),

    // «pruebas» (portal de pruebas externas de la AEAT) o «produccion»
    'entorno' => env('VERIFACTU_ENTORNO', 'pruebas'),

    // Certificado electrónico cualificado del obligado, de su apoderado o de un colaborador social (PEM o P12 convertido a PEM)
    'certificado' => env('VERIFACTU_CERT_RUTA'),
    'certificado_clave' => env('VERIFACTU_CERT_CLAVE'),
    'certificado_privada' => env('VERIFACTU_CERT_KEY_RUTA'),

    // Productor del programa (quien firma la declaración responsable) y datos del sistema que van en cada registro
    'sistema' => [
        'nombre' => 'Nortia',            // máximo 30 caracteres
        'id' => 'LG',                         // código identificador del sistema, 2 caracteres
        'version' => '1.0.0',
        'num_instalacion' => env('VERIFACTU_NUM_INSTALACION', '1'),
        'productor_nombre' => env('VERIFACTU_PRODUCTOR_NOMBRE'),
        'productor_nif' => env('VERIFACTU_PRODUCTOR_NIF'),
    ],

    'urls' => [
        'pruebas' => [
            'servicio' => 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
            'servicio_sello' => 'https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
            'qr' => 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR',
        ],
        'produccion' => [
            'servicio' => 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
            'servicio_sello' => 'https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
            'qr' => 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR',
        ],
    ],

    // Control de flujo de la AEAT: segundos entre envíos (la respuesta puede cambiarlo) y máximo de registros por envío
    'espera_envio' => 60,
    'max_registros_envio' => 1000,
];
