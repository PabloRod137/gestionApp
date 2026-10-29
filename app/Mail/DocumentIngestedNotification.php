<?php

namespace App\Mail;

use App\Models\ExpedientDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentIngestedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public ExpedientDocument $document;
    public string $clientName;
    public string $channelName;

    /**
     * Crea una nueva instancia de la notificación.
     */
    public function __construct(ExpedientDocument $document)
    {
        $this->document = $document;
        $this->clientName = $document->client->razon_social ?? 'Cliente no identificado';
        
        $canales = [
            'email' => 'Correo Electrónico (IMAP)',
            'whatsapp' => 'WhatsApp (API)',
            'escaner' => 'Escáner de Oficina (Físico)',
            'portal' => 'Portal de Clientes'
        ];
        $this->channelName = $canales[$document->canal_entrada] ?? $document->canal_entrada;
    }

    /**
     * Define el sobre del correo.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nortia: Ingesta Automática de Documento - ' . $this->clientName,
        );
    }

    /**
     * Define la vista de contenido del correo.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.document_ingested',
        );
    }

    /**
     * Retorna los adjuntos del correo.
     */
    public function attachments(): array
    {
        return [];
    }
}
