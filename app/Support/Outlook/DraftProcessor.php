<?php

namespace App\Support\Outlook;

use App\Models\CaseActivity;
use App\Models\CommunicationDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Convierte una solicitud de comunicación en un borrador real del correo. Se puede repetir sin duplicar:
 * antes de crear se busca por la clave de idempotencia y después de crear se relee y se comprueba.
 * La comunicación puede llevar el PDF aprobado de una propuesta o ir sin adjuntos.
 */
class DraftProcessor
{
    public const MAX_INTENTOS = 5;

    public function __construct(private DraftGateway $correo) {}

    /** Solicitudes que toca procesar (o reintentar). */
    public function pendientes()
    {
        return CommunicationDraft::query()
            ->where(fn ($q) => $q->where('status', 'pendiente_adaptador')
                ->orWhere(fn ($w) => $w->where('status', 'error')->where('attempts', '<', self::MAX_INTENTOS))
                ->orWhere(fn ($w) => $w->where('status', 'procesando')->where('updated_at', '<', now()->subMinutes(10))))
            ->oldest('id');
    }

    public function procesar(CommunicationDraft $d): CommunicationDraft
    {
        $d = DB::transaction(function () use ($d) {
            $d = CommunicationDraft::whereKey($d->id)->lockForUpdate()->firstOrFail();
            if (! in_array($d->status, ['pendiente_adaptador', 'error', 'procesando'], true)) {
                return $d;
            }
            $d->update(['status' => 'procesando', 'attempts' => $d->attempts + 1]);

            return $d;
        });
        if ($d->status !== 'procesando') {
            return $d;
        }

        try {
            if (! $this->correo->configurado()) {
                throw new OutlookException('El correo de Microsoft 365 todavía no está configurado.');
            }
            $nombre = $pdf = null;
            if ($d->attachment_document_id) {
                $doc = $d->proposal?->pdfDocument;
                if (! $doc || ! Storage::exists($doc->ruta)) {
                    throw new OutlookException('Falta el PDF aprobado.');
                }
                $pdf = Storage::get($doc->ruta);
                $nombre = $doc->nombre;
                if (hash('sha256', $pdf) !== ($d->manifest['attachments'][0]['sha256'] ?? null)) {
                    throw new OutlookException('El PDF ha cambiado desde que se aprobó: no se envía a Microsoft 365. Vuelve a revisarlo.');
                }
            }

            $remoto = $this->correo->buscar($d->idempotency_key)
                ?? $this->correo->crear($d->idempotency_key, $d->recipient, $d->subject, $d->body, $nombre, $pdf);
            $this->correo->verificar($remoto['id'], $d->recipient, $d->subject, $pdf);

            $d->update([
                'status' => 'borrador_creado', 'remote_id' => $remoto['id'], 'remote_web_link' => $remoto['webLink'], 'remote_verified_at' => now(), 'last_error' => null,
            ]);
            CaseActivity::registrar($d->expedient_id ?? $d->proposal->expedient_id, 'BORRADOR_CREADO', 'Borrador creado y verificado en el correo del despacho. No se ha enviado nada.', null, $d->proposal);
        } catch (\Throwable $e) {
            $d->update(['status' => 'error', 'last_error' => substr($e->getMessage(), 0, 500)]);
        }

        return $d->refresh();
    }
}
