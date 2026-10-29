# Declaración responsable del sistema informático de facturación (borrador)

**Borrador sin firmar.** Sigue la estructura de los «Ejemplos de declaraciones responsables» de la AEAT (producto de uso solo VERI\*FACTU). La firma quien figure como **productor** del programa; quién es (el despacho o quien lo desarrolla) **lo debe confirmar el gestor** antes de firmar. Firmar una declaración sin que el programa cumpla es sancionable (según las FAQ de la AEAT): no firmar hasta completar la lista «Antes de firmar» del final.

| Apartado | Contenido |
|---|---|
| 1.a) Nombre del sistema | Nortia |
| 1.b) Código identificador | LG |
| 1.c) Versión concreta | `config('verifactu.sistema.version')` (hoy 1.0.0): cada cambio que afecte a la facturación sube la versión y se firma de nuevo |
| 1.d) Componentes y descripción | Aplicación web (Laravel/PHP) instalada en el servidor del despacho, con base de datos propia. Gestiona expedientes, clientes y facturación del despacho; la facturación crea el registro de facturación en la misma transacción que la factura. Sin componentes de facturación de terceros |
| 1.e) Solo VERI\*FACTU | S: solo funciona como VERI\*FACTU (remite cada registro a la AEAT; no genera registros no verificables ni firma electrónicamente) |
| 1.f) Varios obligados | N: da soporte a la facturación de un único obligado tributario (el despacho) |
| 1.g) Tipos de firma | No aplica (solo VERI\*FACTU): los registros quedan firmados al remitirse con el certificado electrónico cualificado |
| 1.h) Productor | *Por decidir* (nombre o razón social) |
| 1.i) NIF del productor | *Por decidir* |
| 1.j) Dirección postal del productor | *Por decidir* |
| 1.k) Cumplimiento | Se hace constar que el sistema, en la versión indicada, cumple el artículo 29.2.j) de la Ley 58/2003 General Tributaria, el Reglamento aprobado por el RD 1007/2023, la Orden HAC/1177/2024 y las especificaciones de la sede electrónica de la AEAT |
| 1.l) Fecha y lugar | *Al firmar* |
| 2.a) Otros contactos | *Por decidir* |
| 2.b) Direcciones de internet | *Por decidir* |
| 2.c) Cómo se cumple (implementación) | Ver abajo |

## 2.c) Implementaciones relevantes (lo que hace el programa)
- **Una sola unidad transaccional:** expedir la factura (número correlativo por serie, datos congelados) y generar su registro de alta ocurren en la misma transacción de base de datos y bajo un bloqueo que serializa la cadena (`App\Support\Verifactu\Facturacion::emitir`). Si algo falla no se gasta número.
- **Huella:** SHA-256 sobre la cadena de campos de la especificación v0.1.2 de la AEAT, en mayúsculas, encadenada con la huella del registro anterior (el primero se marca `PrimerRegistro`). Las pruebas reproducen los tres ejemplos oficiales (`VerifactuHuellaTest`).
- **Inalterabilidad:** una factura emitida no se modifica ni se borra desde la aplicación (solo cambia su cobro) y los registros de facturación no se pueden modificar ni borrar: lo impide el modelo y, además, **disparadores de la base de datos** (SQLite y MySQL/MariaDB). Una factura se corrige con una rectificativa (por sustitución o por diferencias) y se anula con un registro de anulación.
- **Registros conforme al diseño de la AEAT:** XML validado en las pruebas contra los esquemas oficiales (`SuministroInformacion.xsd`, `SuministroLR.xsd`).
- **QR tributario:** URL del servicio de cotejo con NIF, número, fecha e importe codificados, ISO/IEC 18004 nivel M, 35 × 35 mm con margen, al principio de la factura, con el texto «QR tributario:» y la leyenda «VERI\*FACTU».
- **Remisión a la AEAT:** servicio web SOAP `RegFactuSistemaFacturacion` con certificado electrónico, control de flujo (60 s o lo que indique la AEAT, máximo 1000 registros), reintentos sin pérdida y estado de cada registro (aceptado, aceptado con errores, rechazado) con su CSV.
- **Identificación del sistema:** cada registro lleva el bloque `SistemaInformatico` (nombre, id, versión, número de instalación, productor).
- **Validaciones previas:** NIF del emisor y del receptor con dígito de control, calificación e IVA, importes.

## Antes de firmar
- [ ] Decidir y anotar el **productor** (apartados 1.h–1.j, 2.a, 2.b) y confirmar con el gestor que puede firmarla.
- [ ] Prueba de remisión real en el **entorno de pruebas de la AEAT** con el certificado del despacho (`VERIFACTU_ENTORNO=pruebas`): registros aceptados, anulación y rectificativa. *No se ha podido hacer: no hay certificado aquí.*
- [ ] Revisar con el gestor el tratamiento de retención de IRPF y suplidos en el registro (calificación N1, ImporteTotal sin restar la retención).
- [ ] Revisar con el gestor las funcionalidades de **consulta y exportación de registros** que exige la orden (acceso de la Administración) y el **plazo de conservación**.
- [ ] Probar los disparadores en MySQL/MariaDB (en SQLite están probados).
- [ ] Decidir la política de **subsanación** de registros rechazados (ver `docs/VERIFACTU.md`).
- [ ] Subir la versión (`config/verifactu.php`) y firmar; guardar la declaración y su histórico.
