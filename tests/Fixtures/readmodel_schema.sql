PRAGMA foreign_keys = ON;

CREATE TABLE CLIENTES (
  cliente_id_local TEXT PRIMARY KEY,
  cliente_id_sp INTEGER,
  codigo_cliente TEXT NOT NULL UNIQUE,
  nombre_razon_social TEXT NOT NULL,
  tipo_cliente TEXT,
  nif_cif TEXT,
  email TEXT,
  telefono TEXT,
  ruta_sharepoint TEXT,
  activo INTEGER NOT NULL DEFAULT 1 CHECK (activo IN (0,1)),
  source TEXT NOT NULL,
  source_modified TEXT,
  loaded_at TEXT NOT NULL
);

CREATE TABLE SERVICIOS (
  codigo_servicio TEXT PRIMARY KEY,
  servicio_id_sp INTEGER,
  nombre_servicio TEXT,
  tipo_cliente TEXT,
  tipo_cobro TEXT,
  periodicidad TEXT,
  base_imponible_default REAL,
  iva REAL,
  requiere_validacion INTEGER CHECK (requiere_validacion IN (0,1)),
  activo INTEGER CHECK (activo IN (0,1)),
  documentacion_requerida_texto TEXT,
  accion_sugerida TEXT,
  genera_documento INTEGER CHECK (genera_documento IN (0,1)),
  tipo_servicio TEXT,
  facturable INTEGER CHECK (facturable IN (0,1)),
  source TEXT NOT NULL,
  source_modified TEXT,
  loaded_at TEXT NOT NULL
);

CREATE TABLE EXPEDIENTES (
  expediente_id TEXT PRIMARY KEY,
  expediente_id_sp INTEGER,
  cliente_id_local TEXT,
  codigo_servicio TEXT,
  titulo TEXT,
  estado TEXT,
  responsable TEXT,
  ruta_sharepoint TEXT,
  importe_definitivo REAL,
  fecha_alta TEXT,
  fecha_cierre TEXT,
  source TEXT NOT NULL,
  source_modified TEXT,
  loaded_at TEXT NOT NULL,
  FOREIGN KEY (cliente_id_local) REFERENCES CLIENTES(cliente_id_local),
  FOREIGN KEY (codigo_servicio) REFERENCES SERVICIOS(codigo_servicio)
);

CREATE TABLE SERVICIO_REQUISITOS_DOCUMENTALES (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo_servicio TEXT NOT NULL,
  requisito_codigo TEXT,
  descripcion TEXT,
  obligatorio INTEGER CHECK (obligatorio IN (0,1)),
  condicion TEXT,
  orden INTEGER,
  estado_normalizacion TEXT NOT NULL,
  texto_origen TEXT,
  source TEXT NOT NULL,
  loaded_at TEXT NOT NULL,
  FOREIGN KEY (codigo_servicio) REFERENCES SERVICIOS(codigo_servicio)
);

CREATE TABLE MODELOS_DOCUMENTO (
  codigo_plantilla TEXT PRIMARY KEY,
  tipo_documento TEXT,
  nombre_plantilla TEXT,
  ruta_plantilla TEXT,
  version TEXT,
  estado TEXT,
  source TEXT NOT NULL,
  source_modified TEXT,
  loaded_at TEXT NOT NULL
);

CREATE TABLE SERVICIO_MODELOS_DOCUMENTO (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo_servicio TEXT NOT NULL,
  codigo_plantilla TEXT NOT NULL,
  tipo_documento TEXT,
  prioridad INTEGER,
  obligatorio INTEGER CHECK (obligatorio IN (0,1)),
  estado TEXT,
  source TEXT NOT NULL,
  loaded_at TEXT NOT NULL,
  UNIQUE (codigo_servicio, codigo_plantilla, tipo_documento),
  FOREIGN KEY (codigo_servicio) REFERENCES SERVICIOS(codigo_servicio),
  FOREIGN KEY (codigo_plantilla) REFERENCES MODELOS_DOCUMENTO(codigo_plantilla)
);

CREATE TABLE REGISTRO_DOCUMENTOS (
  documento_id TEXT PRIMARY KEY,
  expediente_id TEXT,
  tipo_documento TEXT,
  nombre_documento TEXT,
  ruta_url TEXT,
  estado TEXT,
  fecha_documento TEXT,
  origen TEXT,
  fecha_ultima_verificacion TEXT,
  source_item_id TEXT,
  loaded_at TEXT NOT NULL,
  FOREIGN KEY (expediente_id) REFERENCES EXPEDIENTES(expediente_id)
);

CREATE TABLE ACTIVIDAD_EXPEDIENTE (
  evento_id TEXT PRIMARY KEY,
  expediente_id TEXT,
  fecha TEXT,
  tipo_evento TEXT,
  descripcion TEXT,
  responsable TEXT,
  estado TEXT,
  origen TEXT,
  loaded_at TEXT NOT NULL,
  FOREIGN KEY (expediente_id) REFERENCES EXPEDIENTES(expediente_id)
);

CREATE TABLE PERSONAS (
  persona_id_local TEXT PRIMARY KEY,
  dni_nie TEXT,
  nombre TEXT,
  apellidos TEXT,
  email TEXT,
  telefono TEXT,
  direccion TEXT,
  source TEXT NOT NULL,
  source_key TEXT NOT NULL UNIQUE,
  source_modified TEXT,
  loaded_at TEXT NOT NULL
);

CREATE TABLE RELACIONES_CLIENTE (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  cliente_id_local TEXT NOT NULL,
  persona_id_local TEXT NOT NULL,
  tipo_relacion TEXT NOT NULL,
  fecha_inicio TEXT,
  fecha_fin TEXT,
  activa INTEGER CHECK (activa IN (0,1)),
  origen TEXT,
  source_key TEXT NOT NULL UNIQUE,
  fecha_ultima_verificacion TEXT,
  loaded_at TEXT NOT NULL,
  FOREIGN KEY (cliente_id_local) REFERENCES CLIENTES(cliente_id_local),
  FOREIGN KEY (persona_id_local) REFERENCES PERSONAS(persona_id_local)
);

CREATE TABLE COMUNIDADES (
  comunidad_id_local TEXT PRIMARY KEY,
  cliente_id_local TEXT UNIQUE,
  nombre TEXT NOT NULL,
  cif TEXT,
  direccion TEXT,
  ruta_sharepoint TEXT,
  source TEXT NOT NULL,
  loaded_at TEXT NOT NULL,
  FOREIGN KEY (cliente_id_local) REFERENCES CLIENTES(cliente_id_local)
);

CREATE TABLE PROPIETARIOS_COMUNIDAD (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  comunidad_id_local TEXT NOT NULL,
  persona_id_local TEXT NOT NULL,
  unidad TEXT,
  coeficiente REAL,
  porcentaje REAL,
  propietario_desde TEXT,
  propietario_hasta TEXT,
  activo INTEGER CHECK (activo IN (0,1)),
  origen TEXT,
  source_key TEXT NOT NULL UNIQUE,
  fecha_ultima_verificacion TEXT,
  loaded_at TEXT NOT NULL,
  FOREIGN KEY (comunidad_id_local) REFERENCES COMUNIDADES(comunidad_id_local),
  FOREIGN KEY (persona_id_local) REFERENCES PERSONAS(persona_id_local)
);

CREATE TABLE EMPLEADOS (
  empleado_id_local TEXT PRIMARY KEY,
  persona_id_local TEXT NOT NULL,
  cliente_empresa_id_local TEXT,
  nss TEXT,
  categoria TEXT,
  convenio TEXT,
  jornada TEXT,
  salario_referencia REAL,
  fecha_alta TEXT,
  fecha_baja TEXT,
  estado TEXT,
  origen TEXT,
  source_key TEXT NOT NULL UNIQUE,
  fecha_ultima_verificacion TEXT,
  loaded_at TEXT NOT NULL,
  FOREIGN KEY (persona_id_local) REFERENCES PERSONAS(persona_id_local),
  FOREIGN KEY (cliente_empresa_id_local) REFERENCES CLIENTES(cliente_id_local)
);

CREATE TABLE FUENTES_CARGA (
  source_id TEXT PRIMARY KEY,
  ruta TEXT NOT NULL,
  sha256 TEXT,
  modified_at TEXT,
  filas_leidas INTEGER,
  filas_cargadas INTEGER,
  estado TEXT NOT NULL,
  detalle TEXT,
  loaded_at TEXT NOT NULL
);

CREATE INDEX idx_expedientes_cliente ON EXPEDIENTES(cliente_id_local);
CREATE INDEX idx_expedientes_servicio ON EXPEDIENTES(codigo_servicio);
CREATE INDEX idx_documentos_expediente ON REGISTRO_DOCUMENTOS(expediente_id);
CREATE INDEX idx_requisitos_servicio ON SERVICIO_REQUISITOS_DOCUMENTALES(codigo_servicio);
CREATE INDEX idx_modelos_servicio ON SERVICIO_MODELOS_DOCUMENTO(codigo_servicio);
CREATE INDEX idx_relaciones_cliente ON RELACIONES_CLIENTE(cliente_id_local);
CREATE INDEX idx_relaciones_persona ON RELACIONES_CLIENTE(persona_id_local);
CREATE INDEX idx_propietarios_comunidad ON PROPIETARIOS_COMUNIDAD(comunidad_id_local);
CREATE INDEX idx_empleados_empresa ON EMPLEADOS(cliente_empresa_id_local);

CREATE VIEW VW_EXPEDIENTE_LECTURA AS
SELECT e.*, c.codigo_cliente, c.nombre_razon_social, c.tipo_cliente,
       s.nombre_servicio, s.documentacion_requerida_texto,
       s.accion_sugerida, s.genera_documento
FROM EXPEDIENTES e
LEFT JOIN CLIENTES c ON c.cliente_id_local = e.cliente_id_local
LEFT JOIN SERVICIOS s ON s.codigo_servicio = e.codigo_servicio;
