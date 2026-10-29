<?php

namespace Tests\Support;

use PDO;

/** Base de lectura INVENTADA con el mismo esquema que la del despacho; nunca datos reales. */
trait BuildsReadModel
{
    private string $path;

    protected function buildReadModel(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'rm').'.db';
        $pdo = new PDO('sqlite:'.$this->path);
        $pdo->exec(file_get_contents(base_path('tests/Fixtures/readmodel_schema.sql')));
        $now = '2026-09-27T10:00:00+00:00';
        $ins = fn (string $sql, array $p) => $pdo->prepare($sql)->execute($p);

        $ins("INSERT INTO CLIENTES VALUES ('2020-001',1,'2020-001','Cliente Uno SL','Empresa','B00000001','uno@example.test','600000001','/c/uno',1,'x','m',?)", [$now]);
        $ins("INSERT INTO CLIENTES VALUES ('2020-002',2,'2020-002','Pepe Prueba','Autónomo',NULL,NULL,NULL,'/c/pepe',1,'x','m',?)", [$now]);
        $ins("INSERT INTO CLIENTES VALUES ('2020-003',3,'2020-003','Comunidad Demo 1','Comunidad','H00000003',NULL,NULL,'/c/com',1,'x','m',?)", [$now]);

        $ins("INSERT INTO SERVICIOS VALUES ('BAJA_AUT',1,'Baja de autónomo','Autonomo','Puntual','Único',60,0.21,1,1,'DNI, fecha de cese','Preparar baja',1,'Facturable',1,'x','m',?)", [$now]);
        $ins("INSERT INTO SERVICIOS VALUES ('BUROFAX',2,'Burofax','Todos','Puntual','Único',90,0.21,1,1,'Factura','Enviar',1,'Facturable',1,'x','m',?)", [$now]);

        $ins("INSERT INTO EXPEDIENTES VALUES ('2026-001',1,'2020-001','BUROFAX','Reclamación','En curso','','/e/1',NULL,'','','x','m',?)", [$now]);
        $ins("INSERT INTO EXPEDIENTES VALUES ('2026-002',2,'2020-002',NULL,'Sin servicio','No iniciado','','/e/2',NULL,'','','x','m',?)", [$now]);
        $ins("INSERT INTO EXPEDIENTES VALUES ('2026-003',3,'2020-002','BAJA_AUT','Baja','Completado','','/e/3',NULL,'','','x','m',?)", [$now]);

        $ins("INSERT INTO SERVICIO_REQUISITOS_DOCUMENTALES (codigo_servicio,requisito_codigo,descripcion,obligatorio,condicion,orden,estado_normalizacion,texto_origen,source,loaded_at) VALUES ('BUROFAX','ACREDITACION_DEUDA','Factura o contrato',1,'',1,'NORMALIZADO','t','x',?)", [$now]);
        $ins("INSERT INTO SERVICIO_REQUISITOS_DOCUMENTALES (codigo_servicio,requisito_codigo,descripcion,obligatorio,condicion,orden,estado_normalizacion,texto_origen,source,loaded_at) VALUES ('BAJA_AUT','PENDIENTE_NORMALIZAR','DNI, fecha de cese',0,'REQUIERE_NORMALIZACION',1,'PENDIENTE_NORMALIZAR','t','x',?)", [$now]);

        $ins("INSERT INTO MODELOS_DOCUMENTO VALUES ('PLT_BUROFAX','RECLAMACION','Burofax','/p/burofax.docx','','ACTIVO','x','m',?)", [$now]);
        $ins("INSERT INTO REGISTRO_DOCUMENTOS VALUES ('DOC-1','2026-001','PRESUPUESTO','Presupuesto.pdf','https://sp.example/doc1','VIGENTE','','SHAREPOINT',NULL,'DOC-1',?)", [$now]);

        $ins("INSERT INTO COMUNIDADES VALUES ('COM-2020-003','2020-003','Comunidad Demo 1','H00000003','','/c/com','x',?)", [$now]);
        $ins("INSERT INTO PERSONAS VALUES ('PER-1','','Ana Propietaria','','ana@example.test','600000009','','x','k1','m',?)", [$now]);
        $ins("INSERT INTO PROPIETARIOS_COMUNIDAD (comunidad_id_local,persona_id_local,unidad,coeficiente,porcentaje,propietario_desde,propietario_hasta,activo,origen,source_key,fecha_ultima_verificacion,loaded_at) VALUES ('COM-2020-003','PER-1','1ºA',NULL,NULL,'','',1,'CSV','k1',NULL,?)", [$now]);
        $pdo = null;
    }

    protected function dropReadModel(): void
    {
        @unlink($this->path);
    }
}
