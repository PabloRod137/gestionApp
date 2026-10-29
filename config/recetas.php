<?php

/**
 * Recetas del despacho para las propuestas automáticas. No contienen datos de clientes: solo códigos de servicios y modelos.
 * Los modelos asignados aquí son una PRIMERA propuesta para que el despacho revise; se pueden cambiar en el panel (Plantillas).
 */
return [
    // modelo que se usa para presupuestar cualquier servicio facturable
    'modelo_presupuesto' => 'WV_GEN_PRESUPUESTO_V1_OBS',

    // código de servicio => códigos de modelo de documento que genera el despacho
    'modelos_por_servicio' => [
        'CERT_DEUDA' => ['WV_COM_CERT_DEUDA_V1_OBS', 'WV_COM_CERT_ESTAR_CORRIENTE_V1_OBS'],
        'ADM_COM_BAS' => ['WV_COM_REPORTE_MENSUAL_V1_OBS'],
        'ADM_COM_EST' => ['WV_COM_REPORTE_MENSUAL_V1_OBS'],
        'ADM_COM_CPX' => ['WV_COM_REPORTE_MENSUAL_V1_OBS'],
        'JUNTA_ORD' => ['WV_COM_CONVOCATORIA_JUNTA_ORDINARIA_V1_OBS'],
        'DERRAMA' => ['WV_COM_COMUNICACION_DERRAMA_V1_OBS'],
        'RECL_IMPAG' => ['WV_COM_RECLAMACION_CUOTAS_V1_OBS'],
        'BUROFAX' => ['WV_GEN_RECLAMACION_FACTURAS_V1_OBS'],
        'CONTR_CIV' => ['WV_GEN_CONTRATO_V1_OBS'],
        'ALTA_TRAB' => ['WV_LAB_SOLICITUD_DATOS_ALTA_TRABAJADOR_V1_OBS'],
        'MOD_100' => ['WV_PAR_SOLICITUD_DOC_RENTA_V1_OBS'],
        'CUOTA_AUT_BAS' => ['WV_FIS_PETICION_DOC_V2_OBS'],
        'CUOTA_AUT_AVZ' => ['WV_FIS_PETICION_DOC_V2_OBS'],
        'CUOTA_EMP_BAS' => ['WV_EMP_SOLICITUD_DOC_CONTABLE_V1_OBS'],
        'CUOTA_EMP_EST' => ['WV_EMP_SOLICITUD_DOC_CONTABLE_V1_OBS'],
        'CUOTA_EMP_CPX' => ['WV_EMP_SOLICITUD_DOC_CONTABLE_V1_OBS'],
    ],
];
