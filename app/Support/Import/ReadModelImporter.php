<?php

namespace App\Support\Import;

use App\Models\Client;
use App\Models\Community;
use App\Models\CommunityOwner;
use App\Models\DocumentRequirement;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Person;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;

/**
 * Importa la base de lectura del despacho (DESPACHO_READMODEL.db, generada desde las listas de SharePoint)
 * a las tablas de la aplicacion. Se puede repetir: cada fila se identifica por su codigo de origen,
 * asi que importar dos veces actualiza y no duplica. La base de origen solo se abre en lectura.
 */
class ReadModelImporter
{
    /** @var array<int, string> filas que no se han podido importar, con el motivo */
    private array $avisos = [];

    /** @return array{contadores: array<string,int>, avisos: array<int,string>, avisos_graves: int} */
    public function importar(string $ruta): array
    {
        if (! is_file($ruta)) {
            throw new \InvalidArgumentException("No existe la base de origen: {$ruta}");
        }
        $this->avisos = [];
        $src = new PDO('sqlite:'.$ruta);
        $src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $src->exec('PRAGMA query_only = ON');

        $c = [];
        DB::transaction(function () use ($src, &$c) {
            $c['clientes'] = $this->clientes($src);
            $c['servicios'] = $this->servicios($src);
            $c['requisitos'] = $this->requisitos($src);
            $c['modelos'] = $this->modelos($src);
            $c['servicio_modelos'] = $this->servicioModelos($src);
            $c['expedientes'] = $this->expedientes($src);
            $c['documentos'] = $this->documentos($src);
            $c['comunidades'] = $this->comunidades($src);
            $c['propietarios'] = $this->propietarios($src);
        });

        \App\Models\Setting::setValue('readmodel_imported_at', now()->toIso8601String());

        return ['contadores' => $c, 'avisos' => $this->avisos, 'avisos_graves' => 0];
    }

    private function filas(PDO $src, string $sql): \Generator
    {
        foreach ($src->query($sql, PDO::FETCH_ASSOC) as $row) {
            yield array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
        }
    }

    private function vacio(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return $v === '' || $v === null ? null : (string) $v;
    }

    private function tipoCliente(?string $t): string
    {
        return match (Str::of((string) $t)->ascii()->lower()->toString()) {
            'autonomo' => 'autonomo',
            'empresa' => 'empresa',
            'comunidad' => 'comunidad',
            'particular' => 'particular',
            default => 'otros',
        };
    }

    private function estadoExpediente(?string $e): string
    {
        return match (Str::of((string) $e)->ascii()->lower()->toString()) {
            'en curso' => 'en_proceso',
            'completado' => 'finalizado',
            default => 'abierto',
        };
    }

    private function clientes(PDO $src): int
    {
        $n = 0;
        foreach ($this->filas($src, 'SELECT * FROM CLIENTES') as $r) {
            Client::withTrashed()->updateOrCreate(['codigo' => $r['codigo_cliente']], [
                'razon_social' => $r['nombre_razon_social'],
                'tipo' => $this->tipoCliente($r['tipo_cliente']),
                'nif' => $this->vacio($r['nif_cif']),
                'email' => $this->vacio($r['email']),
                'telefono' => $this->vacio($r['telefono']),
                'ruta_sharepoint' => $this->vacio($r['ruta_sharepoint']),
                'activo' => (bool) $r['activo'],
            ]);
            $n++;
        }

        return $n;
    }

    private function servicios(PDO $src): int
    {
        $n = 0;
        foreach ($this->filas($src, 'SELECT * FROM SERVICIOS') as $r) {
            $iva = $r['iva'] === null ? null : round((float) $r['iva'] * ($r['iva'] <= 1 ? 100 : 1), 2);
            $service = Service::firstOrNew(['codigo' => $r['codigo_servicio']]);
            $service->fill([
                'nombre' => $r['nombre_servicio'] ?: $r['codigo_servicio'],
                'precio_base' => $r['base_imponible_default'] ?? 0,
                'tipo' => 'sin_cita',
                'activo' => (bool) $r['activo'],
                'tipo_cliente' => $this->vacio($r['tipo_cliente']),
                'tipo_cobro' => $this->vacio($r['tipo_cobro']),
                'periodicidad' => $this->vacio($r['periodicidad']),
                'iva' => $iva,
                'requiere_validacion' => (bool) $r['requiere_validacion'],
                'facturable' => (bool) $r['facturable'],
                'genera_documento' => (bool) $r['genera_documento'],
                'documentacion_requerida' => $this->vacio($r['documentacion_requerida_texto']),
                'accion_sugerida' => $this->vacio($r['accion_sugerida']),
            ]);
            $service->save();
            $n++;
        }

        return $n;
    }

    private function requisitos(PDO $src): int
    {
        $services = Service::pluck('id', 'codigo');
        DocumentRequirement::query()->delete(); // se reconstruye entero: no tiene identificador estable en origen
        $n = 0;
        foreach ($this->filas($src, 'SELECT * FROM SERVICIO_REQUISITOS_DOCUMENTALES ORDER BY codigo_servicio, orden, id') as $r) {
            $id = $services[$r['codigo_servicio']] ?? null;
            if (! $id) {
                $this->avisos[] = "Requisito de un servicio desconocido: {$r['codigo_servicio']}";

                continue;
            }
            $normalizado = $r['estado_normalizacion'] === 'NORMALIZADO';
            DocumentRequirement::create([
                'service_id' => $id,
                'codigo' => $normalizado ? $r['requisito_codigo'] : null,
                'descripcion' => $this->vacio($r['descripcion']),
                // sin normalizar no se sabe si es obligatorio: no se da por opcional
                'obligatorio' => $normalizado ? (bool) $r['obligatorio'] : null,
                'condicion' => $normalizado ? $this->vacio($r['condicion']) : null,
                'orden' => $r['orden'],
                'normalizado' => $normalizado,
            ]);
            $n++;
        }

        return $n;
    }

    private function modelos(PDO $src): int
    {
        $n = 0;
        foreach ($this->filas($src, 'SELECT * FROM MODELOS_DOCUMENTO') as $r) {
            DocumentTemplate::updateOrCreate(['codigo' => $r['codigo_plantilla']], [
                'tipo_documento' => $this->vacio($r['tipo_documento']),
                'nombre' => $r['nombre_plantilla'] ?: $r['codigo_plantilla'],
                'ruta' => $this->vacio($r['ruta_plantilla']),
                'version' => $this->vacio($r['version']),
                'activo' => $r['estado'] === 'ACTIVO',
            ]);
            $n++;
        }

        return $n;
    }

    private function servicioModelos(PDO $src): int
    {
        $services = Service::pluck('id', 'codigo');
        $templates = DocumentTemplate::pluck('id', 'codigo');
        $n = 0;
        foreach ($this->filas($src, 'SELECT * FROM SERVICIO_MODELOS_DOCUMENTO') as $r) {
            if (! isset($services[$r['codigo_servicio']], $templates[$r['codigo_plantilla']])) {
                continue;
            }
            DB::table('service_document_templates')->updateOrInsert(
                ['service_id' => $services[$r['codigo_servicio']], 'document_template_id' => $templates[$r['codigo_plantilla']]],
                ['prioridad' => $r['prioridad'], 'obligatorio' => (bool) $r['obligatorio']],
            );
            $n++;
        }

        return $n;
    }

    private function expedientes(PDO $src): int
    {
        $clients = Client::pluck('id', 'codigo');
        $services = Service::pluck('id', 'codigo');
        $n = 0;
        foreach ($this->filas($src, 'SELECT * FROM EXPEDIENTES') as $r) {
            $clientId = $clients[$r['cliente_id_local']] ?? null;
            if (! $clientId) {
                $this->avisos[] = "Expediente {$r['expediente_id']} sin cliente identificable: no se importa";

                continue;
            }
            Expedient::withTrashed()->updateOrCreate(['codigo' => $r['expediente_id']], [
                'client_id' => $clientId,
                'service_id' => $services[$r['codigo_servicio']] ?? null,
                'titulo' => $r['titulo'] ?: 'Expediente '.$r['expediente_id'],
                'estado' => $this->estadoExpediente($r['estado']),
                'fecha_apertura' => $this->vacio($r['fecha_alta']),
                'fecha_cierre' => $this->vacio($r['fecha_cierre']),
                'ruta_sharepoint' => $this->vacio($r['ruta_sharepoint']),
            ]);
            $n++;
        }

        return $n;
    }

    private function documentos(PDO $src): int
    {
        $exp = Expedient::pluck('id', 'codigo');
        $clientOf = Expedient::pluck('client_id', 'codigo');
        $n = 0;
        foreach ($this->filas($src, 'SELECT * FROM REGISTRO_DOCUMENTOS') as $r) {
            ExpedientDocument::withTrashed()->updateOrCreate(['codigo_origen' => $r['documento_id']], [
                'expedient_id' => $exp[$r['expediente_id']] ?? null,
                'client_id' => $clientOf[$r['expediente_id']] ?? null,
                'nombre' => $r['nombre_documento'] ?: $r['documento_id'],
                'ruta' => null,
                'url_externa' => $this->vacio($r['ruta_url']),
                'tipo_documento' => $this->vacio($r['tipo_documento']),
                'origen' => $this->vacio($r['origen']),
                'visibilidad' => 'interno',
                'canal_entrada' => 'sharepoint',
            ]);
            $n++;
        }

        return $n;
    }

    private function comunidades(PDO $src): int
    {
        $clients = Client::pluck('id', 'codigo');
        $n = 0;
        foreach ($this->filas($src, 'SELECT * FROM COMUNIDADES') as $r) {
            Community::updateOrCreate(['client_id' => $clients[$r['cliente_id_local']] ?? null], [
                'nombre' => $r['nombre'],
                'cif' => $this->vacio($r['cif']),
                'direccion' => $this->vacio($r['direccion']),
                'ruta_sharepoint' => $this->vacio($r['ruta_sharepoint']),
            ]);
            $n++;
        }

        return $n;
    }

    private function propietarios(PDO $src): int
    {
        $communities = Community::with('client:id,codigo')->get()->mapWithKeys(fn ($c) => ['COM-'.$c->client?->codigo => $c->id]);
        $n = 0;
        foreach ($this->filas($src, 'SELECT p.*, pe.nombre, pe.apellidos, pe.dni_nie, pe.email, pe.telefono, pe.direccion, pe.source_key AS persona_key
                FROM PROPIETARIOS_COMUNIDAD p JOIN PERSONAS pe ON pe.persona_id_local = p.persona_id_local') as $r) {
            $communityId = $communities[$r['comunidad_id_local']] ?? null;
            if (! $communityId) {
                $this->avisos[] = "Propietario de una comunidad desconocida: {$r['comunidad_id_local']}";

                continue;
            }
            $person = Person::updateOrCreate(['codigo_origen' => $r['persona_key']], [
                'nombre' => $r['nombre'],
                'apellidos' => $this->vacio($r['apellidos']),
                'dni' => $this->vacio($r['dni_nie']),
                'email' => $this->vacio($r['email']),
                'telefono' => $this->vacio($r['telefono']),
                'direccion' => $this->vacio($r['direccion']),
            ]);
            CommunityOwner::updateOrCreate(['codigo_origen' => $r['source_key']], [
                'community_id' => $communityId,
                'person_id' => $person->id,
                'unidad' => $this->vacio($r['unidad']),
                'coeficiente' => $r['coeficiente'],
                'porcentaje' => $r['porcentaje'],
                'activo' => (bool) $r['activo'],
            ]);
            $n++;
        }

        return $n;
    }
}
