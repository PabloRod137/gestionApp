<?php

/*
| Qué hechos del despacho piden una factura y cuándo se prepara. El programa NUNCA emite solo: prepara borradores
| que una persona revisa y emite (Veri*Factu: una factura emitida ya no se toca). Ver docs/AUTOMATIZACION.md.
*/
return [
    // Momento en que un servicio se factura si el propio servicio no indica otro: 'apertura' (al abrir el expediente)
    // o 'finalizado' (al cerrarlo). Cada servicio puede fijar el suyo en services.momento_facturacion.
    'momento_por_defecto' => env('FACTURACION_MOMENTO', 'finalizado'),

    // Preparar el borrador solo cuando el expediente cumple la regla (servicio con tarifa, facturable, sin factura ya).
    'borradores_automaticos' => (bool) env('FACTURACION_BORRADORES_AUTOMATICOS', true),

    // Borradores de las cuotas periódicas de los clientes el último día de cada mes
    'fin_de_mes_automatico' => (bool) env('FACTURACION_FIN_DE_MES', true),

    // Retención de IRPF que llevan por defecto los borradores (0 hasta confirmarla con el gestor)
    'retencion_irpf' => (float) env('FACTURACION_RETENCION_IRPF', 0),

    // Tipos de servicio que no se facturan por separado
    'sin_factura_aparte' => ['Incluido en cuota', 'No facturable'],
];
