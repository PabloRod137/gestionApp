<?php

namespace App\Support\Outlook;

/**
 * Contrato con el correo del despacho. Solo existen operaciones de borrador: no hay (ni habrá) un método para enviar.
 */
interface DraftGateway
{
    /** ¿Están puestos todos los datos de conexión? */
    public function configurado(): bool;

    /** Borrador ya creado para esta clave de idempotencia, si existe. @return array{id: string, webLink: ?string}|null */
    public function buscar(string $clave): ?array;

    /** @return array{id: string, webLink: ?string} */
    public function crear(string $clave, string $destinatario, string $asunto, string $cuerpo, ?string $nombrePdf, ?string $pdf): array;

    /** Relee el borrador y comprueba que es un borrador, con el destinatario, el asunto y el PDF exactos (o sin adjuntos si no hay PDF). @throws OutlookException */
    public function verificar(string $id, string $destinatario, string $asunto, ?string $pdf): void;
}
