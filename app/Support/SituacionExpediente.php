<?php

namespace App\Support;

use App\Models\CommunicationDraft;
use App\Models\Expedient;

/**
 * La situación de un expediente de un vistazo:
 * qué tengo, qué falta, qué puedo generar, cuál es la siguiente actuación y qué acciones están disponibles.
 * Todo se calcula con reglas; no interviene ninguna IA.
 */
class SituacionExpediente
{
    private const DATOS_CLIENTE = ['nif' => 'NIF / CIF del cliente', 'email' => 'Correo del cliente', 'telefono' => 'Teléfono del cliente', 'direccion' => 'Dirección del cliente'];

    /** @return array{tengo: array, falta: array, generar: array, siguiente: array{titulo: string, detalle: string}, acciones: array} */
    public static function para(Expedient $e): array
    {
        $e->loadMissing('client', 'service.templates', 'service.requirements');
        $c = $e->client;
        $s = $e->service;

        $modelos = $s ? $s->templates->filter(fn ($t) => $t->activo && $t->archivo)->values() : collect();
        $obligatorios = $s ? $s->requirements->where('normalizado', true)->where('obligatorio', true) : collect();
        $faltanDatos = collect(self::DATOS_CLIENTE)->filter(fn ($_, $campo) => blank($c->{$campo}));
        $propuestas = $e->proposals()->get();
        $activa = $propuestas->first(fn ($p) => ! in_array($p->status, ['rejected', 'communication_requested'], true));

        $tengo = [
            ['texto' => 'Documentos registrados', 'valor' => $e->documents()->count()],
            ['texto' => 'Datos del cliente disponibles', 'valor' => count(self::DATOS_CLIENTE) - $faltanDatos->count()],
            ['texto' => 'Actuaciones registradas', 'valor' => $e->activities()->count()],
            ['texto' => 'Propuestas de actuación', 'valor' => $propuestas->count()],
            ['texto' => 'Otros expedientes del cliente', 'valor' => Expedient::where('client_id', $e->client_id)->where('id', '!=', $e->id)->count()],
        ];

        $falta = [];
        if (! $s) {
            $falta[] = ['texto' => 'Servicio del expediente', 'nota' => 'Sin él no se pueden determinar requisitos ni documentos.'];
        }
        foreach ($faltanDatos as $texto) {
            $falta[] = ['texto' => $texto, 'nota' => 'Falta dato'];
        }
        if ($s && $s->genera_documento && $modelos->isEmpty()) {
            $falta[] = ['texto' => 'Modelo de documento con archivo Word', 'nota' => 'El servicio no tiene ninguno asignado.'];
        }
        if ($obligatorios->isNotEmpty()) {
            $falta[] = ['texto' => 'Comprobar la documentación obligatoria del servicio', 'nota' => $obligatorios->count().' documento(s) que pide el servicio'];
        }

        $generar = $modelos->map(fn ($t) => ['texto' => $t->nombre, 'nota' => 'Modelo del servicio'])->all();
        if ($obligatorios->isNotEmpty() && filled($c->email)) {
            $generar[] = ['texto' => 'Solicitud de documentación', 'nota' => 'Lista para preparar el borrador'];
        }

        return [
            'tengo' => $tengo, 'falta' => $falta, 'generar' => $generar,
            'siguiente' => self::siguiente($e, $s, $modelos, $faltanDatos, $activa),
            'acciones' => self::acciones($e, $c, $modelos, $obligatorios),
        ];
    }

    private static function siguiente(Expedient $e, $s, $modelos, $faltanDatos, $activa): array
    {
        if (! $s) {
            return ['titulo' => 'Completar el servicio del expediente', 'detalle' => 'Sin servicio no se pueden determinar requisitos ni documentos.'];
        }
        if ($activa) {
            return match ($activa->status) {
                'proposed' => ['titulo' => 'Aprobar la propuesta', 'detalle' => "«{$activa->title}» espera aprobación."],
                'approved' => ['titulo' => 'Generar el documento', 'detalle' => "«{$activa->title}» está aprobada; falta generar el Word."],
                'generated' => $activa->pdf_document_id
                    ? ['titulo' => 'Revisar y aprobar el documento', 'detalle' => "«{$activa->title}»: Word y PDF listos para aprobar."]
                    : ['titulo' => 'Adjuntar el PDF revisado', 'detalle' => "«{$activa->title}»: revisa el Word y sube su PDF."],
                'document_approved' => ['titulo' => 'Preparar la comunicación', 'detalle' => "«{$activa->title}» está aprobada; falta preparar el correo."],
                default => ['titulo' => 'Revisar la propuesta', 'detalle' => ''],
            };
        }
        $pendiente = CommunicationDraft::whereIn('status', ['pendiente_adaptador', 'error'])
            ->where(fn ($q) => $q->where('expedient_id', $e->id)->orWhereHas('proposal', fn ($p) => $p->where('expedient_id', $e->id)))->exists();
        if ($pendiente) {
            return ['titulo' => 'Crear el borrador en Outlook', 'detalle' => 'Hay una comunicación preparada que aún no es un borrador del correo.'];
        }
        if ($s->genera_documento) {
            return $modelos->isEmpty()
                ? ['titulo' => 'Acreditar el modelo documental', 'detalle' => 'El servicio genera documento pero no tiene un modelo con archivo Word.']
                : ['titulo' => 'Crear una propuesta de documento', 'detalle' => 'El servicio tiene modelos listos para generar.'];
        }
        if ($faltanDatos->isNotEmpty()) {
            return ['titulo' => 'Completar los datos del cliente', 'detalle' => 'Faltan: '.$faltanDatos->implode(', ').'.'];
        }

        return ['titulo' => 'No hay una actuación pendiente', 'detalle' => 'No existe una regla que indique un siguiente paso.'];
    }

    private static function acciones(Expedient $e, $c, $modelos, $obligatorios): array
    {
        $url = filter_var($e->ruta_sharepoint, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', (string) $e->ruta_sharepoint) ? $e->ruta_sharepoint : null;
        $de = fn (array $tipos) => $modelos->contains(fn ($t) => in_array($t->tipo_documento, $tipos, true));
        $comunidad = \App\Support\Comunidades\ReporteMensual::comunidadDe($e);
        $informe = $comunidad
            ? ['activa' => true, 'href' => route('panel.comunidades.reporte', $comunidad->id), 'nota' => 'Cifras y textos del reporte mensual de la comunidad.']
            : ['activa' => false, 'href' => null, 'nota' => 'El informe mensual es para comunidades.'];
        $modelo = fn (array $tipos, string $que, string $tipoSug) => [
            'activa' => $de($tipos), 'href' => '?tipo='.$tipoSug.'#nueva-propuesta',
            'nota' => $de($tipos) ? "Crea una propuesta con el modelo de {$que}." : "El servicio no tiene un modelo de {$que} con archivo Word.",
        ];

        return [
            ['clave' => 'abrir', 'etiqueta' => 'Abrir expediente', 'activa' => (bool) $url, 'href' => $url, 'externa' => true,
                'nota' => $url ? 'Abre la carpeta del expediente en SharePoint.' : 'No consta una dirección web de la carpeta.'],
            ['clave' => 'falta', 'etiqueta' => 'Qué falta', 'activa' => true, 'href' => '#falta', 'nota' => 'Muestra lo que falta en este expediente.'],
            ['clave' => 'solicitar', 'etiqueta' => 'Solicitar documentación', 'activa' => $obligatorios->isNotEmpty() && filled($c->email), 'href' => '?comunicacion=documentacion#comunicacion',
                'nota' => $obligatorios->isNotEmpty() && filled($c->email) ? 'Prepara un borrador con la documentación que pide el servicio.' : 'Falta el correo del cliente o la documentación obligatoria del servicio.'],
            ['clave' => 'preparar', 'etiqueta' => 'Preparar documento', 'activa' => $modelos->isNotEmpty(), 'href' => '#nueva-propuesta',
                'nota' => $modelos->isNotEmpty() ? 'Crea una propuesta con uno de los modelos del servicio.' : 'El servicio no tiene modelos con archivo Word.'],
            ['clave' => 'reclamar', 'etiqueta' => 'Reclamar deuda'] + $modelo(['RECLAMACION'], 'reclamación', 'RECLAMACION'),
            ['clave' => 'certificado', 'etiqueta' => 'Emitir certificado'] + $modelo(['CERTIFICADO'], 'certificado', 'CERTIFICADO'),
            ['clave' => 'presupuesto', 'etiqueta' => 'Presupuesto / hoja de encargo'] + $modelo(['ECONOMICO', 'HOJA_ENCARGO'], 'presupuesto u hoja de encargo', 'ECONOMICO'),
            ['clave' => 'informe', 'etiqueta' => 'Informe mensual'] + $informe,
            ['clave' => 'comunicacion', 'etiqueta' => 'Preparar comunicación', 'activa' => true, 'href' => '?comunicacion=libre#comunicacion', 'nota' => 'Prepara un correo para revisarlo en Outlook.'],
            ['clave' => 'ia', 'etiqueta' => 'Razonar con IA', 'activa' => (bool) config('ia.enabled'), 'href' => '#razonar-ia',
                'nota' => config('ia.enabled') ? 'Solo bajo petición y limitado a este cliente.' : 'La IA local no está activada.'],
        ];
    }
}
