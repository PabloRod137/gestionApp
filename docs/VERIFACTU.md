# Verifactu y facturación electrónica: qué nos obliga y qué conviene añadir

Investigación del 6 de octubre de 2026. Fuentes oficiales (BOE y Agencia Tributaria) para fechas y obligaciones; lo marcado como «a confirmar» no se ha podido leer en la fuente oficial y debe validarlo el gestor del despacho. **No es asesoramiento fiscal.**

## 1. Dos obligaciones distintas (no se sustituyen)

| | **Veri\*Factu / SIF** (antifraude) | **Factura electrónica B2B** (Crea y Crece) |
|---|---|---|
| Qué exige | El programa que emite facturas debe generar un **registro de facturación** por cada factura, encadenado con huella (hash), con **QR** en la factura, y que no se pueda alterar ni borrar sin dejar rastro | Emitir y recibir facturas **en formato estructurado** (UBL, CII, EDIFACT o Facturae, norma EN16931) entre empresarios y profesionales, y comunicar su estado y pago |
| Norma | Ley 11/2021; RD 1007/2023 (reglamento); Orden HAC/1177/2024 (técnica); [RDL 15/2025](https://www.boe.es/buscar/doc.php?id=BOE-A-2025-24446) (aplazamiento) | Ley 18/2022; [RD 238/2026](https://www.boe.es/diario_boe/txt.php?id=BOE-A-2026-7295); Orden HAC/1028/2026 (BOE 5-10-2026) |
| A quién | Quien emite facturas con un programa informático, en territorio común (País Vasco y Navarra tienen TicketBAI). Quedan fuera los inscritos en el SII | Todos los empresarios y profesionales que facturan a otros empresarios o profesionales (no a particulares) |
| Cuándo | **1 enero 2027** si el despacho tributa por **Impuesto sobre Sociedades**; **1 julio 2027** el resto (autónomos en IRPF) | La orden entró en vigor el **6 oct 2026** y empieza a contar el plazo: **12 meses** (>8 M€ de facturación) o **24 meses** el resto, es decir, para el despacho **en torno a octubre de 2028** |

Fuentes: [AEAT, ampliación del plazo](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/nota-informativa-ampliacion-plazo-adaptacion-facturacion.html), [AEAT, preguntas frecuentes](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/cuestiones-generales-ambitos-aplicacion.html), [documentación técnica para desarrolladores](https://www.agenciatributaria.es/AEAT.desarrolladores/Desarrolladores/_menu_/Documentacion/Sistemas_Informaticos_de_Facturacion_y_Sistemas_VERI_FACTU/Sistemas_Informaticos_de_Facturacion_y_Sistemas_VERI_FACTU.html), [Orden HAC/1028/2026](https://www.boe.es/boe/dias/2026/10/05/pdfs/BOE-A-2026-20587.pdf).

Algunos blogs hablan de «aplazamiento a 2028» o de fechas fijas para la factura electrónica: **no coinciden con el BOE**. Valen las fechas de esta tabla.

### Veri\*Factu: detalles técnicos (según la AEAT; leer las especificaciones antes de programar)
- Documentos de la AEAT: servicios web v1.0.3, huella/hash v0.1.2, QR v0.5.0, validación de registros no verificables v0.2 y FAQ de desarrolladores (actualizada el 4-12-2025).
- Cada factura genera un **registro de alta** (y uno de **anulación** si se anula), con la huella del registro anterior: cadena que impide borrar o alterar sin que se note.
- **QR** ISO/IEC 18004 nivel M, de 30 a 40 mm, al principio de la factura, con la leyenda «VERI\*FACTU» si se envía a la AEAT.
- Dos modos: **VERI\*FACTU** (cada registro se envía a la AEAT en el momento; sin firma electrónica propia) y **no verificable** (se guardan en local, con firma electrónica y registro de eventos, y se entregan si la AEAT lo requiere).
- Para enviar hace falta un **certificado electrónico** del despacho (o de un representante apoderado) y hay entorno de pruebas de la AEAT. *A confirmar:* tipo de certificado y alta en el entorno de pruebas.
- El que comercializa el programa debe emitir una **declaración responsable** de que cumple. *A confirmar con el gestor:* si un programa hecho a medida para uso propio del despacho también la necesita y quién figura como «productor» (el despacho, o quien lo desarrolla).
- **Sanciones** (art. 201 bis LGT, según [Quipu](https://getquipu.com/blog/hacienda-multara-autonomos/) y [Economist & Jurist](https://www.economistjurist.es/zreportaje/entrada-en-vigor-de-verifactu-el-nuevo-radar-de-hacienda-que-puede-disparar-multas-fijas-de-50-000-e-por-ejercicio/); no verificadas en el BOE): hasta 50.000 € por ejercicio a quien **use** un programa que no cumple, y hasta 150.000 € por ejercicio al que lo **produce**. El riesgo existe por tener el programa, no solo por un error concreto.

### Factura electrónica B2B: detalles (Orden HAC/1028/2026 y RD 238/2026)
- La AEAT ofrece una **solución pública** gratuita (sintaxis UBL). Quien use programa propio o plataforma privada debe **enviar a la solución pública, a la vez que emite, una copia fiel en UBL**.
- La factura debe poder llevar el **QR de Veri\*Factu** y su URL (la orden lo prevé en el modelo de datos): las dos obligaciones conviven.
- Hay que comunicar el **estado y el pago** de la factura en 4 días hábiles; durante 12 meses tras la entrada en vigor para el pequeño es voluntario (según el resumen leído del RD; *a confirmar*).
- La solución pública debe estar disponible al menos 2 meses antes de la primera aplicación.

## 2. Decisión tomada: Veri*Factu es la guía de toda la facturación

El despacho decidió (6-10-2026) que **la facturación de Nortia se hace conforme a Veri\*Factu**, sea cual sea el programa que se use hoy. Se construyó un sistema informático de facturación en modalidad **«solo VERI\*FACTU»**: cada registro se remite a la AEAT en el momento. Es la modalidad más sencilla (no exige registro de eventos ni firma electrónica propia).

### Qué está hecho (`App\Support\Verifactu`)
| Pieza | Qué hace |
|---|---|
| `Huella` | SHA-256 de la cadena de campos, según la especificación v0.1.2. Pruebas con los 3 ejemplos oficiales |
| `QrTributario` | URL de cotejo (pruebas/producción) y QR nivel M; se lee de vuelta en las pruebas |
| `NifEspanol` | NIF/NIE/CIF con dígito de control (la AEAT rechaza los que no existen) |
| `Facturacion` | **Único sitio que crea registros.** Emitir (numeración correlativa por serie F2026/R2026 sin huecos, datos congelados, registro de alta encadenado, todo en una transacción y con bloqueo), anular, rectificar (sustitución R/S o diferencias R/I), totales por tipo de IVA, retención de IRPF, suplidos (N1) |
| `RegistroXml` | XML de alta y anulación y lote; **validado contra los XSD oficiales** en las pruebas (`resources/verifactu/xsd`) |
| `ClienteAeat` + `EnviadorAeat` | Envío SOAP con certificado, control de flujo (60 s o lo que diga la AEAT, máx. 1000), reintentos sin perder nada, estados (aceptado / con errores / rechazado), CSV, duplicados |
| Comandos | `verifactu:enviar` (cada minuto si `VERIFACTU_ACTIVO=true`) y `verifactu:estado` (comprobación y resumen) |
| Modelo | Facturas: borrador → emitida → anulada; las anteriores quedan «historica» (solo lectura). **Una emitida no se edita ni se borra** (solo su cobro). Los registros no se pueden alterar ni borrar: modelo **y disparadores de base de datos** |
| Pantallas | Borrador (editar/borrar), emitir, anular con motivo, rectificar, cobro, registros con huella y estado de envío; PDF con **QR tributario**; el cliente nunca ve borradores y las anuladas no cuentan como deuda |

### Qué NO se ha podido comprobar
- **Envío real a la AEAT:** no hay certificado electrónico aquí. Se probó con respuestas simuladas (`VerifactuEnvioTest`) y los XML contra los esquemas oficiales. Falta una prueba real en el entorno de pruebas de la AEAT.
- Los disparadores en **MySQL/MariaDB** (en SQLite están probados).
- Tipo de certificado aceptado y alta en el entorno de pruebas.

### Decisiones y límites conocidos (revisar con el gestor)
- **Retención de IRPF:** `ImporteTotal` y el QR llevan base + IVA **sin restar** la retención (criterio habitual); lo que paga el cliente («a cobrar») es total − retención. Confirmar.
- **Suplidos:** se registran como operación **no sujeta (N1)**. Confirmar la calificación correcta.
- **Solo receptores con NIF español.** Un cliente extranjero (otro tipo de identificación) o una factura simplificada (F2) no están soportados todavía.
- **Rechazos de la AEAT:** un registro rechazado se muestra en rojo con su código y descripción y no se puede anular. **Falta la subsanación** (nuevo registro con `RechazoPrevio`/`Subsanacion`): hasta decidir el procedimiento, se resuelve a mano con el gestor.
- **Productor:** si no se indica `VERIFACTU_PRODUCTOR_*`, se usa el despacho (a confirmar). Ver `docs/VERIFACTU_DECLARACION_RESPONSABLE.md`.
- Vigilar que `config/verifactu.php` → `version` suba con cada cambio de facturación (va en cada registro y en la declaración).

### Puesta en marcha
1. Datos del despacho **con NIF válido** (Configuración del panel).
2. `.env`: `VERIFACTU_ENTORNO=pruebas`, `VERIFACTU_CERT_RUTA` (+ `_CLAVE`, `_KEY_RUTA` si el certificado va en PEM aparte), `VERIFACTU_PRODUCTOR_*`, y por último `VERIFACTU_ACTIVO=true`.
3. `php artisan verifactu:estado` debe salir sin errores.
4. Emitir una factura de prueba, comprobar que el registro se acepta en pruebas; después `VERIFACTU_ENTORNO=produccion`.
5. El planificador (`schedule:run` cada minuto) hace el envío.

## 3. Factura electrónica B2B (pendiente de construir)
El programa ya genera los datos necesarios (emisor, receptor, desglose, QR). Falta emitir la factura en **formato estructurado UBL (EN16931)** y enviar **a la vez una copia a la solución pública de la AEAT**, y comunicar el estado y el pago. El plazo para el despacho es de unos **24 meses desde el 6-10-2026** (octubre de 2028), así que se hará cuando la AEAT publique las especificaciones de la solución pública. La orden prevé que la factura estructurada incluya la URL del QR de Veri\*Factu, que ya generamos.

## 4. Otras utilidades de gestión (siguientes pasos posibles)
- **Libro de facturas emitidas** y exportación trimestral para el gestor (IVA 303, retenciones 111, pagos fraccionados 130/202). El Excel actual ya lleva cuota de IVA, retención y situación.
- **Provisiones de fondos** y suplidos como conceptos propios.
- **Vencimientos y recordatorios internos de cobro** (sin enviar correos desde el programa) y conciliación con los pagos de Stripe.
- **Facturas recibidas** de proveedores (lectura de UBL/Facturae).
- **Subsanación de rechazos** y pantalla de incidencias de envío.
- Consulta de facturas en la AEAT (servicio `ConsultaFactuSistemaFacturacion`) para cuadrar el estado local con el de la AEAT.
