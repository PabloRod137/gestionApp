<?php

namespace App\Support\Outlook;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Borradores en el buzón del despacho mediante Microsoft Graph (permiso Mail.ReadWrite sobre un único buzón).
 * Nunca se usa /send: lo que se crea es un borrador que una persona revisa y envía desde Outlook.
 */
class GraphDraftGateway implements DraftGateway
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    /** Propiedad propia del mensaje donde se guarda la clave de idempotencia (GUID fijo de este programa). */
    private const PROPIEDAD = 'String {3f1c2d4e-6b7a-4c8d-9e0f-1a2b3c4d5e6f} Name x-despacho-clave';

    public function configurado(): bool
    {
        $c = config('services.microsoft');

        return ! empty($c['enabled']) && ! empty($c['tenant']) && ! empty($c['client_id']) && ! empty($c['client_secret']) && ! empty($c['mailbox']);
    }

    public function buscar(string $clave): ?array
    {
        $filtro = "singleValueExtendedProperties/Any(ep: ep/id eq '".self::PROPIEDAD."' and ep/value eq '{$clave}')";
        $r = $this->http()->get($this->buzon().'/mailFolders/drafts/messages', ['$filter' => $filtro, '$select' => 'id,webLink', '$top' => 1]);
        $this->comprobar($r, 'buscar el borrador');
        $m = $r->json('value.0');

        return $m ? ['id' => $m['id'], 'webLink' => $m['webLink'] ?? null] : null;
    }

    public function crear(string $clave, string $destinatario, string $asunto, string $cuerpo, ?string $nombrePdf, ?string $pdf): array
    {
        if ($pdf !== null && strlen($pdf) > 3_000_000) {
            throw new OutlookException('El PDF supera los 3 MB: los adjuntos grandes requieren una sesión de subida, que todavía no está implementada.');
        }
        $r = $this->http()->post($this->buzon().'/messages', [
            'subject' => $asunto,
            'body' => ['contentType' => 'Text', 'content' => $cuerpo],
            'toRecipients' => [['emailAddress' => ['address' => $destinatario]]],
            'singleValueExtendedProperties' => [['id' => self::PROPIEDAD, 'value' => $clave]],
        ]);
        $this->comprobar($r, 'crear el borrador');
        $id = $r->json('id');

        if ($pdf === null) {
            return ['id' => $id, 'webLink' => $r->json('webLink')];
        }

        $a = $this->http()->post($this->buzon()."/messages/{$id}/attachments", [
            '@odata.type' => '#microsoft.graph.fileAttachment', 'name' => $nombrePdf, 'contentType' => 'application/pdf', 'contentBytes' => base64_encode($pdf),
        ]);
        $this->comprobar($a, 'adjuntar el PDF');

        return ['id' => $id, 'webLink' => $r->json('webLink')];
    }

    public function verificar(string $id, string $destinatario, string $asunto, ?string $pdf): void
    {
        $r = $this->http()->get($this->buzon()."/messages/{$id}", ['$select' => 'isDraft,subject,toRecipients', '$expand' => 'attachments($select=id,name)']);
        $this->comprobar($r, 'releer el borrador');
        if ($r->json('isDraft') !== true) {
            throw new OutlookException('El mensaje remoto no es un borrador.');
        }
        $para = array_map(fn ($x) => strtolower($x['emailAddress']['address'] ?? ''), $r->json('toRecipients') ?? []);
        if ($para !== [strtolower($destinatario)] || $r->json('subject') !== $asunto) {
            throw new OutlookException('El destinatario o el asunto del borrador remoto no coinciden con lo aprobado.');
        }
        $adjuntos = $r->json('attachments') ?? [];
        if ($pdf === null) {
            if ($adjuntos !== []) {
                throw new OutlookException('El borrador remoto no debería llevar adjuntos.');
            }

            return;
        }
        if (count($adjuntos) !== 1) {
            throw new OutlookException('El borrador remoto debe llevar exactamente un adjunto.');
        }
        $a = $this->http()->get($this->buzon()."/messages/{$id}/attachments/{$adjuntos[0]['id']}");
        $this->comprobar($a, 'releer el adjunto');
        if (hash('sha256', (string) base64_decode((string) $a->json('contentBytes'), true)) !== hash('sha256', $pdf)) {
            throw new OutlookException('El PDF del borrador remoto no coincide con el aprobado.');
        }
    }

    private function buzon(): string
    {
        return self::GRAPH.'/users/'.rawurlencode((string) config('services.microsoft.mailbox'));
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->timeout(30);
    }

    private function token(): string
    {
        return Cache::remember('graph-token', 3000, function () {
            $c = config('services.microsoft');
            $r = Http::asForm()->timeout(30)->post("https://login.microsoftonline.com/{$c['tenant']}/oauth2/v2.0/token", [
                'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials',
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new OutlookException('No se pudo obtener acceso a Microsoft 365 (revisa la configuración de la aplicación).');
            }

            return $r->json('access_token');
        });
    }

    private function comprobar(Response $r, string $que): void
    {
        if ($r->successful()) {
            return;
        }
        // el mensaje no incluye credenciales ni el contenido del correo
        throw new OutlookException("Microsoft 365 no permitió {$que} (código {$r->status()}): ".substr((string) $r->json('error.message', 'sin detalle'), 0, 200));
    }
}
