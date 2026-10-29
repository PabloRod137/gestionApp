<?php

namespace App\Support\Workflow;

use App\Models\CaseActivity;
use App\Models\CommunicationDraft;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Proposal;
use App\Models\User;
use App\Support\GeneradorDocumentos;
use App\Support\Pdf\PdfException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Circuito de trabajo diario (traído del panel V3 del despacho):
 *   propuesta -> aprobada -> documento generado (con su PDF) -> documento aprobado -> comunicación preparada
 * Cada paso lo hace una persona identificada, con control de versiones (nadie pisa el trabajo de otro),
 * revisiones guardadas y huellas de los archivos. Nunca se envía nada: la comunicación queda como solicitud.
 */
class ProposalWorkflow
{
    public function __construct(private GeneradorDocumentos $generador) {}

    /** Huella de todo lo que determina el documento: si cambia, hay que volver a generarlo. */
    public function huella(Expedient $e, ?DocumentTemplate $t): string
    {
        $e->loadMissing('client', 'service');
        $c = $e->client;
        $s = $e->service;

        return hash('sha256', json_encode([
            'exp' => [$e->codigo, $e->titulo, $e->estado, $e->service_id, $e->client_id],
            'cli' => [$c->codigo, $c->razon_social, $c->nif, $c->email, $c->telefono, $c->direccion],
            'srv' => [$s?->codigo, $s?->genera_documento, $s?->accion_sugerida],
            'plt' => [$t?->codigo, $t?->archivo && Storage::exists($t->archivo) ? hash('sha256', Storage::get($t->archivo)) : null],
            // la fecha de hoy no cuenta: cambiaria cada dia y dejaria caducado todo documento; tampoco las cifras del reporte mensual
            // (dependen del mes en curso y las confirma una persona al revisar el documento)
            'despacho' => array_diff_key($this->generador->valores($e), ['fecha_hoy' => 1] + \App\Support\Comunidades\ReporteMensual::CAMPOS),
        ], JSON_UNESCAPED_UNICODE));
    }

    public function crear(Expedient $e, string $titulo, string $finalidad, ?DocumentTemplate $t, User $por): Proposal
    {
        return DB::transaction(function () use ($e, $titulo, $finalidad, $t, $por) {
            $p = Proposal::create([
                'expedient_id' => $e->id, 'document_template_id' => $t?->id, 'title' => $titulo, 'purpose' => $finalidad,
                'status' => 'proposed', 'version' => 1, 'source_hash' => $this->huella($e, $t), 'created_by' => $por->id,
            ]);
            $this->guardar($p, $por, 'PROPUESTA_CREADA', 'Propuesta creada; no se ha ejecutado nada fuera del programa.');

            return $p;
        });
    }

    public function editar(Proposal $p, int $version, string $titulo, string $finalidad, User $por): Proposal
    {
        return $this->mutar($p, $version, ['proposed'], function (Proposal $p) use ($titulo, $finalidad) {
            $p->title = $titulo;
            $p->purpose = $finalidad;
        }, $por, 'PROPUESTA_CORREGIDA');
    }

    public function aprobar(Proposal $p, int $version, User $por): Proposal
    {
        return $this->mutar($p, $version, ['proposed'], function (Proposal $p) use ($por) {
            $p->status = 'approved';
            $p->approved_by = $por->id;
        }, $por, 'PROPUESTA_APROBADA');
    }

    /**
     * @param  array<string, string>  $extras  datos escritos a mano para las marcas que no están guardadas
     */
    public function generar(Proposal $p, int $version, array $extras, User $por, bool $guardarFicha = false): Proposal
    {
        return $this->mutar($p, $version, ['approved'], function (Proposal $p) use ($extras, $por, $guardarFicha) {
            $t = $p->template;
            if (! $t || ! $t->archivo || ! Storage::exists($t->archivo)) {
                throw new WorkflowException('La plantilla de esta propuesta todavía no tiene archivo Word.');
            }
            // antes de generar, para que la huella del documento ya incluya lo que se acaba de completar
            if ($guardarFicha && ($campos = $this->generador->guardarEnFicha($p->expedient, $extras, $por))) {
                CaseActivity::registrar($p->expedient_id, 'DATOS_FICHA_COMPLETADOS', 'Completados en el momento: '.implode(', ', $campos).'.', $por, $p);
            }
            $doc = $this->generador->generar($t, $p->expedient, $por, $extras);
            $doc->update(['origen' => 'PROPUESTA:'.$p->id]);
            $p->generated_document_id = $doc->id;
            $p->document_sha256 = hash('sha256', Storage::get($doc->ruta));
            $p->pdf_document_id = null;
            $p->pdf_sha256 = null;
            // el PDF se crea solo a partir del Word; si no se puede, queda el Word y hay que subir el PDF a mano
            try {
                $pdf = $this->generador->pdfDe($doc, $por);
                $p->pdf_document_id = $pdf->id;
                $p->pdf_sha256 = hash('sha256', Storage::get($pdf->ruta));
            } catch (PdfException $e) {
                CaseActivity::registrar($p->expedient_id, 'PDF_NO_GENERADO', mb_substr($e->getMessage(), 0, 300), $por, $p);
            }
            $p->source_hash = $this->huella($p->expedient, $t);
            $p->status = 'generated';
            $p->document_key = $this->claveDocumento($p);
        }, $por, 'DOCUMENTO_GENERADO', 'Documento generado; falta revisarlo y aprobarlo.');
    }

    /** El PDF lo exporta una persona desde Word una vez revisado el documento; aquí solo se comprueba y se registra. */
    public function adjuntarPdf(Proposal $p, int $version, UploadedFile $pdf, User $por): Proposal
    {
        $bytes = file_get_contents($pdf->getRealPath());
        if (strlen($bytes) > 20_000_000 || ! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -1024), '%%EOF')) {
            throw new WorkflowException('El archivo no es un PDF válido o supera los 20 MB.');
        }

        return $this->mutar($p, $version, ['generated'], function (Proposal $p) use ($bytes, $por) {
            if ($p->pdf_document_id) {
                $viejo = $p->pdfDocument;
                $p->pdf_document_id = null;
                $p->save();
                if ($viejo) {
                    Storage::delete($viejo->ruta);
                    $viejo->forceDelete();
                }
            }
            $ruta = "expedientes/{$p->expedient_id}/propuesta-{$p->id}-".now()->format('Ymd-His').'.pdf';
            Storage::put($ruta, $bytes);
            $doc = ExpedientDocument::create([
                'expedient_id' => $p->expedient_id, 'client_id' => $p->expedient->client_id, 'nombre' => $p->title.' (PDF revisado).pdf', 'ruta' => $ruta,
                'tipo_mime' => 'application/pdf', 'subido_por' => $por->id, 'visibilidad' => 'interno', 'canal_entrada' => 'generado',
                'tipo_documento' => $p->template?->tipo_documento, 'origen' => 'PDF_REVISADO',
            ]);
            $p->pdf_document_id = $doc->id;
            $p->pdf_sha256 = hash('sha256', $bytes);
            $p->document_key = $this->claveDocumento($p);
        }, $por, 'PDF_ADJUNTADO', 'PDF revisado adjuntado.');
    }

    public function revisarDocumento(Proposal $p, int $version, bool $confirmado, User $por): Proposal
    {
        return $this->mutar($p, $version, ['generated'], function (Proposal $p) use ($confirmado, $por) {
            if (! $confirmado) {
                throw new WorkflowException('Confirma que has revisado el documento Word y su PDF.');
            }
            $this->verificarAdjuntos($p);
            $this->verificarVigencia($p);
            $p->status = 'document_approved';
            $p->document_approved_by = $por->id;
            $p->approved_document_key = $p->document_key;
        }, $por, 'DOCUMENTO_APROBADO', 'Aprobado sobre las huellas exactas del Word y del PDF.');
    }

    public function prepararComunicacion(Proposal $p, int $version, array $datos, User $por): Proposal
    {
        $correo = trim((string) ($datos['recipient'] ?? ''));
        $asunto = trim((string) ($datos['subject'] ?? ''));
        $cuerpo = trim((string) ($datos['body'] ?? ''));
        if ($correo === '' || ! preg_match('/^[^\s@,;<>]+@[^\s@,;<>]+\.[^\s@,;<>]+$/', $correo)) {
            throw new WorkflowException('Introduce un único correo válido y revisado.');
        }
        if ($asunto === '' || preg_match('/[\r\n]/', $asunto)) {
            throw new WorkflowException('El asunto es obligatorio y debe ocupar una línea.');
        }
        if ($cuerpo === '') {
            throw new WorkflowException('El cuerpo del mensaje es obligatorio.');
        }
        if (empty($datos['confirmado'])) {
            throw new WorkflowException('Confirma que has revisado destinatario, asunto, cuerpo y adjunto.');
        }
        $comunicacion = ['recipient' => strtolower($correo), 'subject' => $asunto, 'body' => $cuerpo];

        return DB::transaction(function () use ($p, $version, $comunicacion, $por) {
            $p = Proposal::whereKey($p->id)->lockForUpdate()->firstOrFail();
            // reintento exacto tras un fallo: se devuelve lo ya preparado, sin duplicar
            if ($p->status === 'communication_requested') {
                if (hash('sha256', json_encode($comunicacion, JSON_UNESCAPED_UNICODE)) !== hash('sha256', json_encode(
                    ['recipient' => $p->communicationDraft->recipient, 'subject' => $p->communicationDraft->subject, 'body' => $p->communicationDraft->body], JSON_UNESCAPED_UNICODE))) {
                    throw new WorkflowException('Ya hay una solicitud preparada con otro contenido. No se modifica ni se duplica.');
                }

                return $p;
            }

            return $this->mutar($p, $version, ['document_approved'], function (Proposal $p) use ($comunicacion, $por) {
                if ($p->approved_document_key !== $p->document_key) {
                    throw new WorkflowException('El documento aprobado ya no coincide con el actual. Vuelve a revisarlo.');
                }
                $this->verificarAdjuntos($p);
                $this->verificarVigencia($p);
                $manifest = [
                    'schema' => 'panel-despacho/1', 'command' => 'CREATE_OR_UPDATE_OUTLOOK_DRAFT', 'proposal_id' => $p->id, 'case_id' => $p->expedient->codigo,
                    'document_key' => $p->document_key, 'communication' => $comunicacion,
                    // el Word nunca se adjunta al cliente: solo el PDF aprobado
                    'attachments' => [['kind' => 'pdf', 'name' => $p->pdfDocument->nombre, 'sha256' => $p->pdf_sha256]],
                    'send_permitted' => false,
                ];
                $clave = hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE));
                CommunicationDraft::create([
                    'proposal_id' => $p->id, 'idempotency_key' => $clave, 'recipient' => $comunicacion['recipient'], 'subject' => $comunicacion['subject'],
                    'body' => $comunicacion['body'], 'attachment_document_id' => $p->pdf_document_id, 'status' => 'pendiente_adaptador',
                    'manifest' => $manifest, 'requested_by' => $por->id,
                ]);
                $p->status = 'communication_requested';
            }, $por, 'COMUNICACION_PREPARADA', 'Solicitud de borrador preparada. No se ha enviado nada.');
        });
    }

    public function rechazar(Proposal $p, int $version, string $motivo, User $por): Proposal
    {
        if (trim($motivo) === '') {
            throw new WorkflowException('Indica el motivo del rechazo.');
        }

        return $this->mutar($p, $version, ['proposed', 'approved', 'generated', 'document_approved'], function (Proposal $p) use ($motivo) {
            $p->status = 'rejected';
            $p->rejection_reason = trim($motivo);
        }, $por, 'PROPUESTA_RECHAZADA', $motivo);
    }

    // --- internos -------------------------------------------------------------------------------------

    private function claveDocumento(Proposal $p): string
    {
        return hash('sha256', json_encode([$p->source_hash, $p->document_template_id, $p->title, $p->purpose, $p->document_sha256, $p->pdf_sha256]));
    }

    private function verificarAdjuntos(Proposal $p): void
    {
        if (! $p->generatedDocument || ! $p->pdfDocument) {
            throw new WorkflowException('Falta el documento Word o su PDF.');
        }
        foreach ([[$p->generatedDocument, $p->document_sha256], [$p->pdfDocument, $p->pdf_sha256]] as [$doc, $huella]) {
            if (! Storage::exists($doc->ruta) || hash('sha256', Storage::get($doc->ruta)) !== $huella) {
                throw new WorkflowException('Un adjunto ha cambiado o falta. No puede aprobarse ni prepararse.');
            }
        }
    }

    private function verificarVigencia(Proposal $p): void
    {
        if ($this->huella($p->expedient->fresh(), $p->template) !== $p->source_hash) {
            throw new WorkflowException('Los datos del expediente, del cliente o de la plantilla han cambiado desde que se generó el documento. Genéralo de nuevo.');
        }
    }

    /**
     * Cambia una propuesta con control de versión, guarda la revisión y deja constancia de quién lo hizo.
     */
    private function mutar(Proposal $p, int $version, array $estados, callable $cambio, User $por, string $accion, ?string $detalle = null): Proposal
    {
        return DB::transaction(function () use ($p, $version, $estados, $cambio, $por, $accion, $detalle) {
            $p = Proposal::with('expedient.client', 'expedient.service', 'template')->whereKey($p->id)->lockForUpdate()->firstOrFail();
            if ($version !== $p->version) {
                throw new WorkflowException('Otra persona ha cambiado esta propuesta. Actualiza la página antes de continuar.');
            }
            if (! in_array($p->status, $estados, true)) {
                throw new WorkflowException('Esa acción no está disponible en el estado actual ('.$p->etiqueta().').');
            }
            $cambio($p);
            $p->version++;
            $this->guardar($p, $por, $accion, $detalle);

            return $p;
        });
    }

    private function guardar(Proposal $p, User $por, string $accion, ?string $detalle): void
    {
        $p->save();
        $p->revisions()->create([
            'version' => $p->version, 'user_id' => $por->id, 'created_at' => now(),
            'data' => $p->only(['title', 'purpose', 'status', 'document_template_id', 'document_sha256', 'pdf_sha256', 'document_key']),
        ]);
        CaseActivity::registrar($p->expedient_id, $accion, $detalle, $por, $p);
    }
}
